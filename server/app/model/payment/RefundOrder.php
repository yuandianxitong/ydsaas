<?php
declare(strict_types=1);

namespace app\model\payment;

use core\base\Model;

class RefundOrder extends Model
{
    protected $name = 'refund_orders';
    protected $autoWriteTimestamp = 'datetime';

    /**
     * refund_orders 表无 deleted_at 列（退款单 append-only，不软删）。
     * 关闭继承自 core\base\Model 的 SoftDelete 行为。
     */
    protected $deleteTime = false;

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS    = 'success';
    const STATUS_FAILED     = 'failed';

    const TYPE_SAAS     = 'saas';
    const TYPE_BUSINESS = 'business';

    public static function generateRefundNo(string $channel): string
    {
        $prefix = match ($channel) {
            'wechat' => 'RW',
            'alipay' => 'RA',
            default  => 'RX',
        };
        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
