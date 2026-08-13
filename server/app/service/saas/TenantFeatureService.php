<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\entitlement\Entitlement;
use core\exception\BusinessException;

/**
 * 兼容层：仅返回当前租户已激活权益的 code 字符串数组，
 * 用于现有 `saas.features: string[]` 接口字段与 v-feature 指令。
 *
 * @deprecated 新代码请直接用 EntitlementService::list() / has() 拿到富对象。
 *             本类在 Phase 2 改前端时一起移除。
 */
class TenantFeatureService extends Service
{
    protected EntitlementService $entitlementService;

    /** @return string[] */
    public function listFeatures(?int $tenantId = null): array
    {
        if ($tenantId === null) {
            $tenant = \core\tenant\TenantContext::current();
            $tenantId = $tenant?->id() ?? 0;
        }
        return array_map(
            fn (Entitlement $e) => $e->code,
            $this->entitlementService->list((int) $tenantId)
        );
    }

    public function hasFeature(string $code, ?int $tenantId = null): bool
    {
        if ($code === '') {
            return true;
        }
        if ($tenantId === null) {
            $tenantId = \core\tenant\TenantContext::current()?->id() ?? 0;
        }
        return $this->entitlementService->has((int) $tenantId, $code);
    }

    /**
     * 检查当前租户是否拥有 $codes 中的任一 feature。
     * 空数组视为 true（和前端 hasAnyFeature 一致）。
     */
    public function hasAnyFeature(array $codes, ?int $tenantId = null): bool
    {
        if (empty($codes)) {
            return true;
        }
        if ($tenantId === null) {
            $tenantId = \core\tenant\TenantContext::current()?->id() ?? 0;
        }
        return $this->entitlementService->hasAny((int) $tenantId, $codes);
    }

    /**
     * 断言当前租户必须拥有指定 feature。否则抛 BusinessException（由全局
     * 异常处理器转成 403 响应）。
     */
    public function requireFeature(string $code, ?int $tenantId = null): void
    {
        if (!$this->hasFeature($code, $tenantId)) {
            throw new BusinessException('当前套餐未开通该功能：' . $code, 403);
        }
    }
}
