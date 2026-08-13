<?php
declare(strict_types=1);

namespace app\repository\payment;

use app\model\payment\PaymentOrder;
use core\base\Repository;
use think\Model;

class PaymentOrderRepository extends Repository
{
    protected function getModel(): Model
    {
        return new PaymentOrder();
    }

    /**
     * 根据订单号查找
     */
    public function findByOrderNo(string $orderNo): ?Model
    {
        return $this->query()->where('order_no', $orderNo)->find();
    }

    /**
     * 根据订单号查找并加行锁（用于回调处理）
     */
    public function findByOrderNoForUpdate(string $orderNo): ?Model
    {
        return $this->query()->where('order_no', $orderNo)->lock(true)->find();
    }

    /**
     * 创建支付订单并返回模型实例
     *
     * 复用基类 create() 自动注入 tenant_id，再返回模型实例。
     */
    public function createOrder(array $data): Model
    {
        $row = $this->create($data);
        /** @var PaymentOrder $order */
        $order = $this->query()->where('id', $row['id'])->find();
        return $order;
    }

    /**
     * 根据订单号更新订单数据
     */
    public function updateByOrderNo(string $orderNo, array $data): bool
    {
        return $this->query()->where('order_no', $orderNo)->update($data) !== false;
    }
}
