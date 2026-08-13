<?php
declare(strict_types=1);

namespace app\service\payment;

use core\base\Service;
use core\exception\BusinessException;
use app\model\payment\RefundOrder;
use app\repository\payment\RefundOrderRepository;
use app\repository\payment\PaymentOrderRepository;
use app\repository\saas\SaasOrderRepository;
use app\repository\saas\TenantRepository;
use core\saas\payment\SaasPaymentGateway;
use core\payment\PaymentManager;
use think\facade\Db;
use think\facade\Event;

class RefundService extends Service
{
    protected RefundOrderRepository  $refundOrderRepository;
    protected SaasOrderRepository    $saasOrderRepository;
    protected PaymentOrderRepository $paymentOrderRepository;
    protected TenantRepository       $tenantRepository;

    /**
     * 对 SaaS 订单发起退款
     *
     * @param int    $saasOrderId  saas_orders.id
     * @param int    $amount       退款金额（分）
     * @param string $reason       退款原因
     * @param int    $operatedBy   操作人 platform_admin.id
     * @return array  退款记录
     */
    public function refundSaasOrder(
        int $saasOrderId,
        int $amount,
        string $reason,
        int $operatedBy
    ): array {
        // 1. 查原始订单
        $order = $this->saasOrderRepository->find($saasOrderId);
        if (!$order) {
            throw new BusinessException('SaaS 订单不存在');
        }
        if ((int) $order['status'] !== 2) {
            throw new BusinessException('只有已支付的订单才能退款');
        }

        $channel  = (string) ($order['payment_channel'] ?? '');
        $orderNo  = (string) ($order['order_no'] ?? '');
        $tenantId = (int) ($order['tenant_id'] ?? 0);

        // 原始订单金额（分）
        $totalAmountYuan   = (float) $order['amount'];
        $totalAmountCents  = (int) round($totalAmountYuan * 100);

        // 2. 校验可退金额
        $alreadyRefundedCents = $this->refundOrderRepository->getRefundedAmount(
            RefundOrder::TYPE_SAAS,
            $saasOrderId
        );
        $refundableCents = $totalAmountCents - $alreadyRefundedCents;
        if ($amount <= 0 || $amount > $refundableCents) {
            throw new BusinessException(
                sprintf('退款金额不合法，可退最多 %d 分', $refundableCents)
            );
        }

        $refundNo      = RefundOrder::generateRefundNo($channel);
        $refundAmountYuan = bcdiv((string) $amount, '100', 2);

        // 3. 创建退款记录（processing）
        $refundData = $this->refundOrderRepository->create([
            'refund_no'          => $refundNo,
            'order_type'         => RefundOrder::TYPE_SAAS,
            'original_order_id'  => $saasOrderId,
            'tenant_id'          => $tenantId,
            'refund_amount'      => $amount,
            'reason'             => $reason,
            'status'             => RefundOrder::STATUS_PROCESSING,
            'payment_channel'    => $channel,
            'channel_refund_no'  => '',
            'operated_by'        => $operatedBy,
            'error_msg'          => '',
        ]);
        $refundId = (int) $refundData['id'];

        // 4. 调用支付网关
        try {
            $result = SaasPaymentGateway::channel($channel)->refund([
                'out_trade_no'  => $orderNo,
                'out_refund_no' => $refundNo,
                'refund_amount' => (float) $refundAmountYuan,
                'total_amount'  => $totalAmountYuan,
                'reason'        => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->refundOrderRepository->update($refundId, [
                'status'    => RefundOrder::STATUS_FAILED,
                'error_msg' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw new BusinessException('退款失败：' . $e->getMessage());
        }

        // 5. 退款成功
        $channelRefundNo = (string) ($result['raw']['refund_id'] ?? $result['out_trade_no'] ?? '');
        $this->refundOrderRepository->update($refundId, [
            'status'            => RefundOrder::STATUS_SUCCESS,
            'channel_refund_no' => $channelRefundNo,
            'refunded_at'       => date('Y-m-d H:i:s'),
        ]);

        // 6. 全额退款时将原订单标为已退款，并冻结租户
        $newAlreadyRefundedCents = $alreadyRefundedCents + $amount;
        if ($newAlreadyRefundedCents >= $totalAmountCents) {
            $this->saasOrderRepository->update($saasOrderId, ['status' => 4]);

            // 冻结租户（硬开关置 0）
            if ($tenantId > 0) {
                $this->tenantRepository->update($tenantId, ['status' => 0]);
                \core\tenant\middleware\TenantContextMiddleware::clearTenantCacheById($tenantId);
            }
        }

        // 7. 触发退款成功事件
        Event::trigger('refund.success', [
            'refund_id'         => $refundId,
            'order_type'        => RefundOrder::TYPE_SAAS,
            'original_order_id' => $saasOrderId,
            'tenant_id'         => $tenantId,
            'refund_amount'     => $amount,
        ]);

        return $this->refundOrderRepository->find($refundId) ?? $refundData;
    }

    /**
     * 对业务订单（payment_orders）发起退款
     *
     * @param int    $paymentOrderId  payment_orders.id
     * @param int    $amount          退款金额（分）
     * @param string $reason          退款原因
     * @param int    $operatedBy      操作人 admin.id
     * @param int    $tenantId        所属租户 ID（用于 scope 校验）
     * @return array  退款记录
     */
    public function refundBusinessOrder(
        int $paymentOrderId,
        int $amount,
        string $reason,
        int $operatedBy,
        int $tenantId = 0
    ): array {
        // 1. 查原始订单
        $order = $this->paymentOrderRepository->find($paymentOrderId);
        if (!$order) {
            throw new BusinessException('支付订单不存在');
        }
        if ($tenantId > 0 && (int) ($order['tenant_id'] ?? 0) !== $tenantId) {
            throw new BusinessException('支付订单不存在');
        }
        if (($order['status'] ?? '') !== 'paid') {
            throw new BusinessException('只有已支付的订单才能退款');
        }

        $channel = (string) ($order['channel'] ?? '');
        $orderNo = (string) ($order['order_no'] ?? '');

        // 原始订单金额（分）
        $totalAmountYuan  = (float) ($order['total_amount'] ?? 0);
        $totalAmountCents = (int) round($totalAmountYuan * 100);

        // 2. 校验可退金额
        $alreadyRefundedCents = $this->refundOrderRepository->getRefundedAmount(
            RefundOrder::TYPE_BUSINESS,
            $paymentOrderId
        );
        $refundableCents = $totalAmountCents - $alreadyRefundedCents;
        if ($amount <= 0 || $amount > $refundableCents) {
            throw new BusinessException(
                sprintf('退款金额不合法，可退最多 %d 分', $refundableCents)
            );
        }

        $refundNo         = RefundOrder::generateRefundNo($channel);
        $refundAmountYuan = (float) bcdiv((string) $amount, '100', 2);

        // 3. 创建退款记录（processing）
        $refundData = $this->refundOrderRepository->create([
            'refund_no'          => $refundNo,
            'order_type'         => RefundOrder::TYPE_BUSINESS,
            'original_order_id'  => $paymentOrderId,
            'tenant_id'          => $tenantId,
            'refund_amount'      => $amount,
            'reason'             => $reason,
            'status'             => RefundOrder::STATUS_PROCESSING,
            'payment_channel'    => $channel,
            'channel_refund_no'  => '',
            'operated_by'        => $operatedBy,
            'error_msg'          => '',
        ]);
        $refundId = (int) $refundData['id'];

        // 4. 调用支付网关
        try {
            $result = PaymentManager::channel($channel)->refund([
                'out_trade_no'  => $orderNo,
                'out_refund_no' => $refundNo,
                'refund_amount' => $refundAmountYuan,
                'total_amount'  => $totalAmountYuan,
                'reason'        => $reason,
            ]);
        } catch (\Throwable $e) {
            $this->refundOrderRepository->update($refundId, [
                'status'    => RefundOrder::STATUS_FAILED,
                'error_msg' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw new BusinessException('退款失败：' . $e->getMessage());
        }

        // 5. 退款成功：更新退款记录 + 更新原支付订单
        $channelRefundNo         = (string) ($result['raw']['refund_id'] ?? $result['out_trade_no'] ?? '');
        $newTotalRefundedCents   = $alreadyRefundedCents + $amount;
        $newTotalRefundedYuan    = round($newTotalRefundedCents / 100, 2);
        $isFullRefund            = $newTotalRefundedCents >= $totalAmountCents;

        $this->refundOrderRepository->update($refundId, [
            'status'            => RefundOrder::STATUS_SUCCESS,
            'channel_refund_no' => $channelRefundNo,
            'refunded_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->paymentOrderRepository->update($paymentOrderId, array_filter([
            'refund_amount' => $newTotalRefundedYuan,
            'status'        => $isFullRefund ? 'refunded' : null,
            'refunded_at'   => $isFullRefund ? date('Y-m-d H:i:s') : null,
        ], fn($v) => $v !== null));

        // 6. 触发退款成功事件
        Event::trigger('refund.success', [
            'refund_id'         => $refundId,
            'order_type'        => RefundOrder::TYPE_BUSINESS,
            'original_order_id' => $paymentOrderId,
            'tenant_id'         => $tenantId,
            'refund_amount'     => $amount,
        ]);

        return $this->refundOrderRepository->find($refundId) ?? $refundData;
    }

    /**
     * 退款列表（分页）
     */
    public function getRefundList(array $params): array
    {
        return $this->refundOrderRepository->getSearchList($params);
    }

    /**
     * 退款详情
     */
    public function getRefundDetail(int $id): array
    {
        $row = $this->refundOrderRepository->find($id);
        if (!$row) {
            throw new BusinessException('退款记录不存在');
        }
        return $row;
    }
}
