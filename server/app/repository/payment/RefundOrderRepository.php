<?php
declare(strict_types=1);

namespace app\repository\payment;

use core\base\Repository;
use app\model\payment\RefundOrder;
use think\Model;

class RefundOrderRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new RefundOrder();
    }

    public function findByRefundNo(string $refundNo): ?array
    {
        $result = $this->query()->where('refund_no', $refundNo)->find();
        return $result ? (is_array($result) ? $result : $result->toArray()) : null;
    }

    /**
     * 查询某笔原始订单已成功退款的总金额（单位：分）
     */
    public function getRefundedAmount(string $orderType, int $originalOrderId): int
    {
        return (int) $this->query()
            ->where('order_type', $orderType)
            ->where('original_order_id', $originalOrderId)
            ->where('status', RefundOrder::STATUS_SUCCESS)
            ->sum('refund_amount');
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 15): array
    {
        $page  = (int) ($params['page'] ?? $page);
        $limit = (int) ($params['limit'] ?? $limit);

        $query = $this->query()->order('id', 'desc');

        if (!empty($params['order_type'])) {
            $query->where('order_type', $params['order_type']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (isset($params['tenant_id'])) {
            $query->where('tenant_id', (int) $params['tenant_id']);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('refund_no', "%{$params['keyword']}%");
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)->select()->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }
}
