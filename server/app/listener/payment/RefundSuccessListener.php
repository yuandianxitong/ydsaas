<?php
declare(strict_types=1);

namespace app\listener\payment;

use think\facade\Event;
use think\facade\Log;

/**
 * 退款成功监听器
 *
 * 监听 'refund.success' 事件，触发 'message.event.dispatch'
 * 以推送退款成功消息通知。
 *
 * 期望的事件数据格式：
 * [
 *   'refund_no'   => 'REF20240101001',
 *   'amount'      => 9900,          // 分，或浮点元
 *   'reason'      => '用户申请退款',
 *   'refund_time' => '2024-01-01 12:00:00',
 *   'tenant_id'   => 1,
 *   'receivers'   => ['phone' => '138...', 'openid' => 'oXXX'],  // 可选
 * ]
 */
class RefundSuccessListener
{
    public function handle(array $event): void
    {
        Log::info('退款成功', [
            'refund_no' => $event['refund_no'] ?? '',
            'amount'    => $event['amount'] ?? 0,
        ]);

        $tenantId = (int) ($event['tenant_id'] ?? 0);
        if (!$tenantId) {
            Log::warning('RefundSuccessListener: 缺少 tenant_id，跳过消息推送');
            return;
        }

        // 将金额格式化为元（支持分/元两种输入）
        $amount = $event['amount'] ?? 0;
        if (is_int($amount) && $amount > 1000) {
            // 看起来是分，转换为元
            $amountYuan = number_format($amount / 100, 2);
        } else {
            $amountYuan = number_format((float) $amount, 2);
        }

        Event::trigger('message.event.dispatch', [
            'event_code' => 'refund_success',
            'tenant_id'  => $tenantId,
            'receivers'  => $event['receivers'] ?? [],
            'variables'  => [
                'refund_no'   => $event['refund_no'] ?? '',
                'amount'      => $amountYuan,
                'reason'      => $event['reason'] ?? '',
                'refund_time' => $event['refund_time'] ?? date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
