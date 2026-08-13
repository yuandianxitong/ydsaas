<?php
declare(strict_types=1);

namespace core\license;

/**
 * 商业能力软降级守卫：授权无效时返回 false，不阻断核心交易。
 */
class LicenseGuard
{
    protected static ?LicenseClient $client = null;

    protected static function client(): LicenseClient
    {
        return self::$client ??= new LicenseClient();
    }

    public static function status(): array
    {
        return self::client()->evaluate();
    }

    public static function isProEnabled(): bool
    {
        return self::client()->isProEnabled();
    }

    /**
     * Pro 插件 code 列表（可按需扩展）
     * Community 可继续使用 coupon 等基础营销；高级能力可挂在此列表。
     */
    public static function proPluginCodes(): array
    {
        return [
            // 示例：后续把高价值插件移入 Site Pro 包后在此声明
            // 'flash_sale', 'group_buy', 'distribution_pro',
        ];
    }

    public static function canUsePlugin(string $code): bool
    {
        if (!in_array($code, self::proPluginCodes(), true)) {
            return true;
        }
        return self::isProEnabled();
    }
}
