<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\saas\payment;

use core\payment\PaymentInterface;
use core\payment\driver\AlipayDriver;
use core\payment\driver\WechatPayDriver;
use core\exception\BusinessException;
use think\facade\Config;
use think\facade\Db;

/**
 * SaaS 级支付网关
 *
 * 与 core\payment\PaymentManager 的区别：
 *   - PaymentManager 从 system_configs 表读配置（租户级，会被 tenant scope 污染）
 *   - SaasPaymentGateway 从 config/saas.php → .env 读配置（平台级，永远不受 scope 影响）
 *
 * 复用同一个 PaymentInterface 契约 + 同一组 driver 类，只换配置源。
 *
 * 测试时可通过 setDriver('wechat', new MockDriver()) 注入 Mock。
 */
class SaasPaymentGateway
{
    /** @var array<string, PaymentInterface> */
    protected static array $instances = [];

    public static function channel(string $channel): PaymentInterface
    {
        if (isset(self::$instances[$channel])) {
            return self::$instances[$channel];
        }

        self::$instances[$channel] = self::createDriver($channel);
        return self::$instances[$channel];
    }

    /**
     * 测试/调试用：手动注入 driver（主要给 MockDriver 用）
     */
    public static function setDriver(string $channel, PaymentInterface $driver): void
    {
        self::$instances[$channel] = $driver;
    }

    public static function reset(): void
    {
        self::$instances = [];
    }

    /**
     * 给定 channel，返回完整 notify_url
     */
    public static function notifyUrl(string $channel): string
    {
        $base = (string) Config::get('saas.payment.notify_base_url', '');
        $path = (string) Config::get("saas.payment.{$channel}.notify_path", '');
        if ($base === '' || $path === '') {
            throw new BusinessException("SaaS 支付回调 URL 未配置：{$channel}");
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    protected static function createDriver(string $channel): PaymentInterface
    {
        return match ($channel) {
            'wechat' => self::createWechat(),
            'alipay' => self::createAlipay(),
            default  => throw new BusinessException("不支持的 SaaS 支付渠道：{$channel}"),
        };
    }

    protected static function createWechat(): PaymentInterface
    {
        $envCfg = (array) Config::get('saas.payment.wechat', []);

        $enabled = self::dbConfig('platform_pay_wechat_enabled', $envCfg['enabled'] ?? false);
        if (empty($enabled)) {
            throw new BusinessException('SaaS 微信支付未启用');
        }

        return new WechatPayDriver([
            'app_id'           => self::dbConfig('platform_pay_wechat_app_id', $envCfg['app_id'] ?? ''),
            'mch_id'           => self::dbConfig('platform_pay_wechat_mch_id', $envCfg['mch_id'] ?? ''),
            'api_v3_key'       => self::dbConfig('platform_pay_wechat_api_key', $envCfg['api_v3_key'] ?? ''),
            'serial_no'        => self::dbConfig('platform_pay_wechat_serial_no', $envCfg['serial_no'] ?? ''),
            'private_key_path' => self::dbConfig('platform_pay_wechat_cert_key_path', $envCfg['private_key_path'] ?? ''),
            'cert_path'        => self::dbConfig('platform_pay_wechat_cert_path', $envCfg['cert_path'] ?? ''),
            'notify_url'       => self::notifyUrl('wechat'),
        ]);
    }

    protected static function createAlipay(): PaymentInterface
    {
        $envCfg = (array) Config::get('saas.payment.alipay', []);

        $enabled = self::dbConfig('platform_pay_alipay_enabled', $envCfg['enabled'] ?? false);
        if (empty($enabled)) {
            throw new BusinessException('SaaS 支付宝未启用');
        }

        return new AlipayDriver([
            'app_id'      => self::dbConfig('platform_pay_alipay_app_id', $envCfg['app_id'] ?? ''),
            'private_key' => self::dbConfig('platform_pay_alipay_private_key', $envCfg['private_key'] ?? ''),
            'public_key'  => self::dbConfig('platform_pay_alipay_public_key', $envCfg['public_key'] ?? ''),
            'notify_url'  => self::notifyUrl('alipay'),
        ]);
    }

    /**
     * Read platform config from DB (tenant_id=0), fallback to $default.
     */
    protected static function dbConfig(string $key, mixed $default = ''): string
    {
        try {
            $row = Db::table('system_configs')
                ->where('tenant_id', 0)
                ->where('config_key', $key)
                ->where('status', 1)
                ->value('config_value');

            return ($row !== null && $row !== '') ? (string) $row : (string) $default;
        } catch (\Throwable) {
            return (string) $default;
        }
    }
}
