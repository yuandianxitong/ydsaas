<?php
declare(strict_types=1);

namespace core\payment\driver;

use core\payment\PaymentInterface;

/**
 * 租户级支付 Mock 驱动（仅限 APP_DEBUG=true 时可用）
 *
 * 用于本地开发 / CI 环境模拟整套支付回调链路，绕过真实微信/支付宝网关：
 * - create() 返回固定 mock 支付参数（不发起任何外部请求）
 * - verifyNotify() 直接透传调用方传入的 out_trade_no/total_amount，不做验签
 *
 * 结构参照 core\saas\payment\driver\MockDriver（平台侧同名先例），
 * 按 core\payment\PaymentInterface 契约逐方法实现，供
 * app\service\payment\PaymentService::handleNotify() 的真实落账链路直接消费。
 *
 * 是否可用由 core\payment\PaymentManager 在 channel('mock') 时按
 * app()->isDebug() 网关控制，本类自身不做二次判断。
 */
class MockPayDriver implements PaymentInterface
{
    public function create(array $order): array
    {
        return [
            'trade_type' => 'mock',
            'data' => [
                'mock' => true,
                'out_trade_no' => (string) ($order['out_trade_no'] ?? ''),
            ],
        ];
    }

    public function query(string $outTradeNo): array
    {
        return [
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'mock_tx_' . $outTradeNo,
            'total_amount' => '0.00',
            'status' => 'pending',
            'raw' => [],
        ];
    }

    public function refund(array $refund): array
    {
        $outTradeNo = (string) ($refund['out_trade_no'] ?? '');

        return [
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'mock_tx_refund_' . $outTradeNo,
            'refund_amount' => (string) ($refund['refund_amount'] ?? '0'),
            'status' => 'success',
            'raw' => [],
        ];
    }

    public function verifyNotify(array $params): array
    {
        $outTradeNo = (string) ($params['out_trade_no'] ?? '');

        return [
            'out_trade_no' => $outTradeNo,
            'trade_no' => 'mock_tx_' . $outTradeNo,
            'total_amount' => (string) ($params['total_amount'] ?? '0'),
            'status' => 'paid',
            'raw' => $params,
        ];
    }

    public function successResponse(): string
    {
        return 'mock_ok';
    }

    public function getDriver(): string
    {
        return 'mock';
    }
}
