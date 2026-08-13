<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\saas\SubscriptionRepository;
use app\repository\saas\TenantRepository;
use app\model\saas\Plan;
use think\facade\Db;

/**
 * SubscriptionService
 *
 * 管理租户订阅的生命周期：
 *   - createInitial: 创建初始订阅（试用/正式），同步 tenants.expires_at / plan_id / storage_limit_bytes
 *   - extend: 续费/升级（基于当前 ends_at 延长，或 now 开始如已过期）
 *     自动判断续费 vs 升级（套餐变则是升级）
 *   - getCurrent / listOfTenant / markRefunded
 *
 * 所有状态变更方法都在 DB 事务内完成。
 */
class SubscriptionService extends Service
{
    protected SubscriptionRepository $subscriptionRepository;
    protected TenantRepository $tenantRepository;
    protected EntitlementService $entitlementService;

    /**
     * 创建初始订阅（试用或正式），并同步 tenants.expires_at / plan_id / storage_limit_bytes。
     * 由 TenantService.create() 在新建租户时调用。
     */
    public function createInitial(
        int $tenantId,
        int $planId,
        int $days,
        int $type = 1,
        int $operatorId = 0
    ): array {
        if (!in_array($type, [1, 2], true)) {
            throw new BusinessException('type 必须是 1（试用）或 2（正式）');
        }
        if ($days <= 0) {
            throw new BusinessException('订阅天数必须 > 0');
        }

        $plan = Plan::find($planId);
        if (!$plan) {
            throw new BusinessException('套餐不存在');
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $endsAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

            $sub = $this->subscriptionRepository->create([
                'tenant_id'   => $tenantId,
                'plan_id'     => $planId,
                'type'        => $type,
                'starts_at'   => $now,
                'ends_at'     => $endsAt,
                'status'      => 1,
                'order_id'    => 0,
                'operator_id' => $operatorId,
                'remark'      => $type === 1 ? '试用' : '初始正式订阅',
            ]);

            $this->tenantRepository->update($tenantId, [
                'plan_id'             => $planId,
                'expires_at'          => $endsAt,
                'storage_limit_bytes' => (int) $plan->storage_limit_bytes,
            ]);

            Db::commit();
            \core\tenant\middleware\TenantContextMiddleware::clearTenantCacheById($tenantId);
            $this->entitlementService->flush($tenantId);
            return $sub;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 续费订阅：在当前 ends_at 基础上延长 $months 个月
     * 如果当前已过期（now > ends_at），则从 now 开始计算新的 ends_at
     * 如果套餐变了，自动识别为升级（type=4）而不是续费（type=3）
     */
    public function extend(
        int $tenantId,
        int $planId,
        int $months,
        int $orderId = 0,
        int $operatorId = 0
    ): array {
        if ($months <= 0) {
            throw new BusinessException('续费月数必须 > 0');
        }
        $plan = Plan::find($planId);
        if (!$plan) {
            throw new BusinessException('套餐不存在');
        }

        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant) {
            throw new BusinessException('租户不存在');
        }

        Db::startTrans();
        try {
            $currentExpiresAt = $tenant['expires_at'] ?? null;
            $baseTs = $currentExpiresAt ? strtotime((string) $currentExpiresAt) : false;
            if (!$baseTs || $baseTs < time()) {
                $baseTs = time();
            }
            $newEndsAt = date('Y-m-d H:i:s', strtotime("+{$months} months", $baseTs));

            $currentPlanId = (int) ($tenant['plan_id'] ?? 0);
            $type = ($currentPlanId !== $planId && $currentPlanId !== 0) ? 4 : 3;
            $remark = $type === 4 ? "升级套餐（从 {$currentPlanId} 到 {$planId}）" : '续费';

            // 结束之前的 active 订阅
            $current = $this->subscriptionRepository->currentOfTenant($tenantId);
            if ($current) {
                $this->subscriptionRepository->update((int) $current['id'], ['status' => 2]);
            }

            // 新建订阅
            $sub = $this->subscriptionRepository->create([
                'tenant_id'   => $tenantId,
                'plan_id'     => $planId,
                'type'        => $type,
                'starts_at'   => date('Y-m-d H:i:s', $baseTs),
                'ends_at'     => $newEndsAt,
                'status'      => 1,
                'order_id'    => $orderId,
                'operator_id' => $operatorId,
                'remark'      => $remark,
            ]);

            // 同步 tenant
            $this->tenantRepository->update($tenantId, [
                'plan_id'             => $planId,
                'expires_at'          => $newEndsAt,
                'storage_limit_bytes' => (int) $plan->storage_limit_bytes,
            ]);

            // 旧 addon 系统（plan_features / tenant_addons）已在 Stage 6 下线；
            // 新插件系统的 tenant_plugins.expired_at 在 activatePurchased 时已正确设置，
            // 套餐切换不影响单独购买的插件到期时间，故无需联动。

            Db::commit();
            \core\tenant\middleware\TenantContextMiddleware::clearTenantCacheById($tenantId);
            $this->entitlementService->flush($tenantId);
            return $sub;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function getCurrent(int $tenantId): ?array
    {
        return $this->subscriptionRepository->currentOfTenant($tenantId);
    }

    public function listOfTenant(int $tenantId, int $page = 1, int $size = 20): array
    {
        return $this->subscriptionRepository->listOfTenant($tenantId, $page, $size);
    }

    public function markRefunded(int $subscriptionId): bool
    {
        return $this->subscriptionRepository->update($subscriptionId, ['status' => 3]);
    }

    /**
     * 获取所有上架中的套餐列表（给租户端套餐选择器用）。
     * features 字段已在 v2.1.0 移除（迁移到 plugin_grants 表）；如租户端需要展示
     * 套餐授权的插件，查 plugin_grants + plugins 联表。
     */
    public function getAvailablePlans(): array
    {
        return Plan::where('status', 1)
            ->order('sort', 'asc')
            ->field('id,code,name,description,price_monthly,price_yearly,storage_limit_bytes,sort')
            ->select()
            ->toArray();
    }
}
