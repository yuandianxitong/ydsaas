<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\saas\SaasOrderRepository;
use app\repository\saas\TenantRepository;
use app\repository\plugin\PluginRepository;
use app\model\saas\Plan;
use think\facade\Db;
use core\saas\payment\SaasPaymentGateway;

/**
 * SaasOrderService
 *
 * M3A 范围：订单数据建模 + 状态机，不含支付 SDK 集成。
 * M3B 会在此基础上集成 WeChat/Alipay prepay + callback。
 *
 * 订单生命周期：
 *   1=待支付 → 2=已支付 (支付成功，触发 SubscriptionService.extend)
 *              → 3=已取消 (超时 / 用户取消)
 *              → 4=已退款 (已支付后退款)
 *
 * 本阶段手工调用 markPaid / markCancelled / markRefunded 模拟状态机。
 */
class SaasOrderService extends Service
{
    protected SaasOrderRepository $saasOrderRepository;
    protected TenantRepository $tenantRepository;
    protected SubscriptionService $subscriptionService;
    protected PluginRepository $pluginRepository;
    protected \app\repository\saas\PlanRepository $planRepository;

    /**
     * 创建订单（待支付状态）
     *
     * @param string $channel wechat / alipay / offline（平台线下收款）
     */
    public function createOrder(
        int $tenantId,
        int $planId,
        int $months,
        int $type = 2,
        string $channel = 'wechat',
        string $method = 'native'
    ): array {
        if ($months <= 0) {
            throw new BusinessException('购买月数必须 > 0');
        }
        if (!in_array($type, [1, 2, 3, 4], true)) {
            throw new BusinessException('type 必须是 1/2/3/4');
        }
        if (!in_array($channel, ['wechat', 'alipay', 'offline'], true)) {
            throw new BusinessException('payment_channel 必须是 wechat / alipay / offline');
        }

        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant) {
            throw new BusinessException('租户不存在');
        }

        $plan = Plan::find($planId);
        if (!$plan) {
            throw new BusinessException('套餐不存在');
        }

        // 计算金额：月数 × monthly_price
        $amount = round(((float) $plan->price_monthly) * $months, 2);

        // 生成订单号：时间戳 + 4 位随机
        $orderNo = date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        // 线下单给更长待支付窗口；线上仍 30 分钟
        $expireSeconds = $channel === 'offline' ? '+7 days' : '+30 minutes';

        return $this->saasOrderRepository->create([
            'order_no'        => $orderNo,
            'tenant_id'       => $tenantId,
            'plan_id'         => $planId,
            'type'            => $type,
            'months'          => $months,
            'amount'          => $amount,
            'paid_amount'     => 0,
            'payment_channel' => $channel,
            'payment_method'  => $channel === 'offline' ? 'manual' : $method,
            'prepay_id'       => '',
            'transaction_id'  => '',
            'status'          => 1,  // 待支付
            'expired_at'      => date('Y-m-d H:i:s', strtotime($expireSeconds)),
        ]);
    }

    /**
     * 平台线下收款：创建 offline 订单并立即标记已支付（触发 SubscriptionService.extend）。
     *
     * 用于新建正式租户开通、存量租户线下续费；勿与 createInitial(type=2) 叠用，避免时长翻倍。
     */
    public function createAndMarkPaidOffline(
        int $tenantId,
        int $planId,
        int $months,
        int $type = 2,
        string $transactionId = '',
        string $remark = ''
    ): array {
        $order = $this->createOrder($tenantId, $planId, $months, $type, 'offline', 'manual');
        $tx = trim($transactionId);
        if ($tx === '') {
            $note = trim($remark);
            $tx = 'offline:' . ($note !== '' ? mb_substr($note, 0, 80) : 'manual');
        }

        return $this->markPaid((int) $order['id'], $tx);
    }

    /**
     * 为已创建的订单发起支付（调用网关生成 prepay_id / code_url）
     *
     * @param int $orderId 订单 ID
     * @return array [order, payment_data]
     */
    public function createPayment(int $orderId, int $expectedTenantId = 0): array
    {
        $order = $this->saasOrderRepository->find($orderId);
        if (!$order) {
            throw new BusinessException('订单不存在');
        }
        // 服务层 tenant 防护：调用者传 expectedTenantId 时强制校验归属
        if ($expectedTenantId > 0 && (int) $order['tenant_id'] !== $expectedTenantId) {
            throw new BusinessException('订单不存在');
        }
        if ((int) $order['status'] !== 1) {
            throw new BusinessException('订单状态不可支付：' . $order['status']);
        }
        $expiredAt = (string) ($order['expired_at'] ?? '');
        if ($expiredAt !== '' && $expiredAt !== '0000-00-00 00:00:00') {
            $expiredTs = strtotime($expiredAt);
            if ($expiredTs !== false && $expiredTs < time()) {
                throw new BusinessException('订单已过期，请重新下单');
            }
        }

        $channel = (string) $order['payment_channel'];
        if ($channel === 'offline') {
            throw new BusinessException('线下订单请使用平台标记已支付，不可发起在线支付');
        }
        $driver = SaasPaymentGateway::channel($channel);

        $payload = [
            'out_trade_no' => $order['order_no'],
            'total_amount' => $order['amount'],
            'subject'      => '元点Admin SaaS 订阅',
            'body'         => '套餐订购（' . $order['months'] . ' 个月）',
            'trade_type'   => $order['payment_method'] ?: 'native',
        ];

        $result = $driver->create($payload);

        // 回写 prepay_id（如果 driver 有返回）
        $prepayId = $result['data']['prepay_id'] ?? '';
        if ($prepayId !== '') {
            $this->saasOrderRepository->update($orderId, ['prepay_id' => $prepayId]);
        }

        return [
            'order'        => $this->saasOrderRepository->find($orderId),
            'payment_data' => $result,
        ];
    }

    /**
     * 支付回调处理（由 SaasPayNotifyController 调用）
     *
     * 流程：
     *   1) 调用 driver.verifyNotify 校验签名并拿到 out_trade_no + status
     *   2) 根据 out_trade_no 找 order
     *   3) 幂等：如果 status 已 = 2 直接返回 true（不重复触发 extend）
     *   4) 如果 status = 1 且回调 status = paid，调用 markPaid
     *   5) 非 paid 通知（如已退款/已关闭终态）直接 return true，ACK 平台不重试
     *
     * @param string $channel wechat / alipay
     * @param array  $params  原始回调参数
     * @return bool 是否成功入账（幂等重复视为成功）
     */
    public function handleCallback(string $channel, array $params): bool
    {
        $driver = SaasPaymentGateway::channel($channel);
        $verified = $driver->verifyNotify($params);

        $outTradeNo = (string) ($verified['out_trade_no'] ?? '');
        if ($outTradeNo === '') {
            throw new BusinessException('回调缺少 out_trade_no');
        }

        $order = $this->saasOrderRepository->findByOrderNo($outTradeNo);
        if (!$order) {
            throw new BusinessException('订单不存在：' . $outTradeNo);
        }

        // 幂等：已支付直接返回 true
        if ((int) $order['status'] === 2) {
            return true;
        }

        // 回调状态必须是 paid 才入账；非 paid 视为合法终态通知，直接 ACK 避免平台重试
        if (($verified['status'] ?? '') !== 'paid') {
            return true;
        }

        // 金额校验：防止篡改。转换成整数分以避免 IEEE 754 double 精度陷阱
        // （例 abs(99.99 - 99.98) 在 double 下等于 0.010000000000005116）。
        // 允许 1 分钱的舍入误差。
        $expectedCents = (int) round(((float) $order['amount']) * 100);
        $actualCents = (int) round(((float) ($verified['total_amount'] ?? 0)) * 100);
        if (abs($expectedCents - $actualCents) > 1) {
            throw new BusinessException(sprintf(
                '订单金额不一致：expected=%.2f actual=%.2f',
                ((float) $order['amount']),
                ((float) ($verified['total_amount'] ?? 0))
            ));
        }

        $tradeNo = (string) ($verified['trade_no'] ?? '');
        if ($tradeNo === '') {
            throw new BusinessException('支付回调缺少 trade_no');
        }

        try {
            $this->markPaid((int) $order['id'], $tradeNo);
        } catch (BusinessException $e) {
            // 并发回调 TOCTOU 防护：另一个线程可能已经入账
            $fresh = $this->saasOrderRepository->find((int) $order['id']);
            if ($fresh && (int) $fresh['status'] === 2) {
                return true;
            }
            throw $e;
        }
        return true;
    }

    /**
     * 租户自己的插件购买订单（saas_orders type=4），含插件名，分页。
     * @return array{list: array<int,array<string,mixed>>, pagination: array{current_page:int,per_page:int,total:int,last_page:int}}
     */
    /**
     * @param array{status?: int|null, keyword?: string} $filters
     */
    public function listPluginOrders(int $tenantId, int $page, int $limit, array $filters = []): array
    {
        $result = $this->saasOrderRepository->listPluginOrdersOfTenant($tenantId, $page, $limit, $filters);
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($r) => (int) ($r['plugin_id'] ?? 0),
            $result['list']
        ))));
        $names = $ids ? $this->pluginRepository->namesByIds($ids) : [];
        foreach ($result['list'] as &$row) {
            $row['plugin_name'] = $names[(int) ($row['plugin_id'] ?? 0)] ?? '';
        }
        unset($row);
        return $result;
    }

    /**
     * 创建插件单独购买订单（Stage 6+）。
     *
     * $pluginId 写在 plugin_id 列上（type=4 增值插件订单）。
     * markPaid 读 months 传给 activatePurchased 算 expired_at。
     */
    public function createPluginOrder(
        int $tenantId,
        int $pluginId,
        int $months,
        float $amount,
        string $channel = 'wechat',
        string $method = 'native'
    ): array {
        if ($months <= 0) {
            throw new BusinessException('购买月数必须 > 0');
        }
        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant) {
            throw new BusinessException('租户不存在');
        }

        $orderNo = date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return $this->saasOrderRepository->create([
            'order_no'         => $orderNo,
            'tenant_id'        => $tenantId,
            'plan_id'          => (int) ($tenant['plan_id'] ?? 0),
            'type'             => 4,
            'plugin_id'        => $pluginId,
            'months'           => $months,
            'amount'           => round($amount, 2),
            'paid_amount'      => 0,
            'payment_channel'  => $channel,
            'payment_method'   => $method,
            'prepay_id'        => '',
            'transaction_id'   => '',
            'status'           => 1,
            'expired_at'       => date('Y-m-d H:i:s', strtotime('+30 minutes')),
        ]);
    }

    /**
     * 标记订单已支付 + 触发订阅延长
     */
    public function markPaid(int $orderId, string $transactionId = ''): array
    {
        Db::startTrans();
        try {
            $order = $this->saasOrderRepository->find($orderId);
            if (!$order) {
                throw new BusinessException('订单不存在');
            }
            if ((int) $order['status'] !== 1) {
                throw new BusinessException('订单状态不可支付：' . $order['status']);
            }

            $this->saasOrderRepository->update($orderId, [
                'status'         => 2,
                'paid_amount'    => $order['amount'],
                'transaction_id' => $transactionId,
                'paid_at'        => date('Y-m-d H:i:s'),
            ]);

            if ((int) $order['type'] === 4) {
                // 插件单独购买订单：激活到 tenant_plugins（Stage 6 切换）
                $pluginId = (int) ($order['plugin_id'] ?? 0);
                if ($pluginId > 0) {
                    $tenantPluginService = app(\app\service\plugin\TenantPluginService::class);
                    $tenantPluginService->activatePurchased(
                        (int) $order['tenant_id'],
                        $pluginId,
                        (int) ($order['months'] ?? 1)
                    );
                }
            } else {
                // 套餐订单：触发订阅延长
                $this->subscriptionService->extend(
                    (int) $order['tenant_id'],
                    (int) $order['plan_id'],
                    (int) $order['months'],
                    $orderId,
                    0
                );
            }

            Db::commit();
            return $this->saasOrderRepository->find($orderId) ?? $order;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function markCancelled(int $orderId): bool
    {
        $order = $this->saasOrderRepository->find($orderId);
        if (!$order) {
            throw new BusinessException('订单不存在');
        }
        if ((int) $order['status'] !== 1) {
            throw new BusinessException('订单状态不可取消：' . $order['status']);
        }
        return $this->saasOrderRepository->update($orderId, ['status' => 3]);
    }

    public function markRefunded(int $orderId): bool
    {
        $order = $this->saasOrderRepository->find($orderId);
        if (!$order) {
            throw new BusinessException('订单不存在');
        }
        if ((int) $order['status'] !== 2) {
            throw new BusinessException('只有已支付的订单可以退款');
        }
        return $this->saasOrderRepository->update($orderId, ['status' => 4]);
    }

    public function show(int $orderId, int $expectedTenantId = 0): array
    {
        $order = $this->saasOrderRepository->find($orderId);
        if (!$order) {
            throw new BusinessException('订单不存在');
        }
        if ($expectedTenantId > 0 && (int) $order['tenant_id'] !== $expectedTenantId) {
            throw new BusinessException('订单不存在');
        }
        return $this->attachDisplayNames([$order])[0];
    }

    public function pendingRenewalOfTenant(int $tenantId): ?array
    {
        return $this->saasOrderRepository->findPendingRenewalOfTenant($tenantId);
    }

    public function paginate(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $size = (int) ($params['limit'] ?? $params['size'] ?? 20);
        $where = [];

        if (!empty($params['tenant_id'])) {
            $where[] = ['tenant_id', '=', (int) $params['tenant_id']];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['order_no'])) {
            $where[] = ['order_no', 'like', '%' . $params['order_no'] . '%'];
        }
        if (!empty($params['plan_id'])) {
            $where[] = ['plan_id', '=', (int) $params['plan_id']];
        }

        $result = $this->saasOrderRepository->paginate($where, $page, $size);
        $result['list'] = $this->attachDisplayNames($result['list'] ?? []);
        return $result;
    }

    /**
     * 平台列表/详情展示用：批量补 tenant_name / plan_name / plugin_name（type=4）。
     * 只增字段不改结构，租户端调用方不受影响。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachDisplayNames(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }
        $collect = static fn (string $key): array => array_values(array_unique(array_filter(array_map(
            static fn ($r) => (int) ($r[$key] ?? 0),
            $rows
        ))));

        $tenantNames = $this->tenantRepository->namesByIds($collect('tenant_id'));
        $planNames   = $this->planRepository->namesByIds($collect('plan_id'));
        $pluginNames = $this->pluginRepository->namesByIds($collect('plugin_id'));

        foreach ($rows as &$row) {
            $row['tenant_name'] = $tenantNames[(int) ($row['tenant_id'] ?? 0)] ?? '';
            $row['plan_name']   = $planNames[(int) ($row['plan_id'] ?? 0)] ?? '';
            $row['plugin_name'] = $pluginNames[(int) ($row['plugin_id'] ?? 0)] ?? '';
        }
        unset($row);
        return $rows;
    }
}
