<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\marketplace;

use core\base\Service;
use core\exception\BusinessException;

/**
 * marketplace plugin 装前兼容性校验：基于 marketplace_app_cache.compatible
 * 字段做 hard reject + 提供 isCompatible() 给 CatalogService 计算 compatible 字段时复用。
 */
class PluginCompatibilityChecker extends Service
{
    /**
     * 判断本地 framework-saas 版本是否在 plugin 声明的 min/max 区间内。
     * minVer / maxVer 为 null 或空串视作"无约束"。
     */
    public function isCompatible(string $localVer, ?string $minVer, ?string $maxVer): bool
    {
        if ($minVer !== null && $minVer !== '' && version_compare($localVer, $minVer, '<')) {
            return false;
        }
        if ($maxVer !== null && $maxVer !== '' && version_compare($localVer, $maxVer, '>')) {
            return false;
        }
        return true;
    }

    /**
     * 依据 marketplace_app_cache 行的 compatible 字段做 hard reject。
     * cache row 由 CatalogService 在 sync 时按 isCompatible() 算好写入。
     *
     * @throws BusinessException 不兼容时抛 422
     */
    public function ensureCompatible(array $cacheRow, string $localFwSaasVersion): void
    {
        if ((int) ($cacheRow['compatible'] ?? 1) === 0) {
            $name = (string) ($cacheRow['app_name'] ?? $cacheRow['app_code'] ?? '应用');
            throw new BusinessException(
                "[{$name}] 与当前 framework-saas v{$localFwSaasVersion} 不兼容，请先升级 framework-saas",
                422
            );
        }
    }
}
