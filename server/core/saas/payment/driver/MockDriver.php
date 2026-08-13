<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\saas\payment\driver;

use core\payment\PaymentInterface;

/**
 * 测试用 Mock Driver
 *
 * - create() 返回固定 prepay_id / code_url
 * - verifyNotify() 直接回传 params（不验签）
 * - 测试中通过 SaasPaymentGateway::setDriver('wechat', new MockDriver()) 注入
 */
class MockDriver implements PaymentInterface
{
    public function __construct(protected string $channel = 'mock') {}

    public function create(array $order): array
    {
        return [
            'trade_type' => 'native',
            'data' => [
                'code_url'  => 'weixin://mock/' . $order['out_trade_no'],
                'prepay_id' => 'mock_prepay_' . $order['out_trade_no'],
            ],
        ];
    }

    public function query(string $outTradeNo): array
    {
        return [
            'out_trade_no' => $outTradeNo,
            'trade_no'     => 'mock_tx_' . $outTradeNo,
            'total_amount' => '0.00',
            'status'       => 'pending',
            'raw'          => [],
        ];
    }

    public function refund(array $refund): array
    {
        return [
            'out_trade_no'  => $refund['out_trade_no'] ?? '',
            'trade_no'      => 'mock_tx_refund',
            'refund_amount' => $refund['refund_amount'] ?? '0',
            'status'        => 'success',
            'raw'           => [],
        ];
    }

    public function verifyNotify(array $params): array
    {
        return [
            'out_trade_no' => (string) ($params['out_trade_no'] ?? ''),
            'trade_no'     => (string) ($params['trade_no'] ?? 'mock_tx'),
            'total_amount' => (string) ($params['total_amount'] ?? '0'),
            'status'       => 'paid',
            'raw'          => $params,
        ];
    }

    public function successResponse(): string
    {
        return 'mock_ok';
    }

    public function getDriver(): string
    {
        return $this->channel;
    }
}
