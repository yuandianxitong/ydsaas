<?php
declare(strict_types=1);

namespace app\model\message;

use core\base\Model;

class MessageEvent extends Model
{
    protected $name = 'message_events';
    protected $autoWriteTimestamp = 'datetime';
    protected $json = ['variable_mapping'];

    public static function supportedEvents(): array
    {
        return [
            'order_paid' => ['label' => '支付成功', 'variables' => ['order_no', 'amount', 'product_name', 'pay_time']],
            'refund_success' => ['label' => '退款成功', 'variables' => ['refund_no', 'amount', 'reason', 'refund_time']],
            'refund_failed' => ['label' => '退款失败', 'variables' => ['refund_no', 'amount', 'reason']],
            'subscription_expiring' => ['label' => '订阅即将到期', 'variables' => ['tenant_name', 'plan_name', 'expire_date', 'days_left']],
            'subscription_expired' => ['label' => '订阅已过期', 'variables' => ['tenant_name', 'plan_name', 'expired_date']],
            'user_registered' => ['label' => '用户注册', 'variables' => ['username', 'register_time']],
        ];
    }
}
