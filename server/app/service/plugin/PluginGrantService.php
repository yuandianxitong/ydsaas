<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\model\plugin\Plugin;
use app\model\plugin\TenantPlugin;
use app\repository\plugin\PluginGrantRepository;
use app\repository\plugin\PluginRepository;
use app\repository\plugin\TenantPluginRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\AppMenuInstaller;

/**
 * 套餐 ↔ 插件 授权关系管理。
 *
 * 设计：
 *   - 不做"增量更新"，每次保存按 diff 算出 add/remove/change，事务内全替换
 *   - 公共 diff 算法是 static 纯函数，便于单测
 *   - sync() 为编排入口，把 PUT 提交的 plugin_ids/auto_enable_ids 转成最终落表行
 */
class PluginGrantService extends Service
{
    protected PluginGrantRepository $grantRepo;
    protected PluginRepository $pluginRepo;
    protected TenantPluginRepository $tenantPluginRepo;
    protected AppMenuInstaller $appMenuInstaller;
    protected \app\service\saas\EntitlementService $entitlementService;
    protected \app\repository\saas\TenantRepository $tenantRepository;

    /**
     * 计算两份授权快照的差异。纯函数，便于单测。
     *
     * @param array<int, array{plugin_id: int, plugin_code: string, auto_enable: int}> $old
     * @param array<int, array{plugin_id: int, plugin_code: string, auto_enable: int}> $new
     * @return array{added: array, removed: array, changed: array}
     */
    public static function diff(array $old, array $new): array
    {
        $oldMap = [];
        foreach ($old as $row) {
            $oldMap[$row['plugin_id']] = $row;
        }
        $newMap = [];
        foreach ($new as $row) {
            $newMap[$row['plugin_id']] = $row;
        }

        $added = $removed = $changed = [];
        foreach ($newMap as $id => $row) {
            if (!isset($oldMap[$id])) {
                $added[] = $row;
            } elseif ((int) $oldMap[$id]['auto_enable'] !== (int) $row['auto_enable']) {
                $changed[] = $row;
            }
        }
        foreach ($oldMap as $id => $row) {
            if (!isset($newMap[$id])) {
                $removed[] = $row;
            }
        }
        return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * 同步某套餐的授权：plugin_ids + auto_enable_ids → plugin_grants 全替换。
     *
     * @param int $planId
     * @param array<int, int> $pluginIds 要授权的 plugin.id 集合
     * @param array<int, int> $autoEnableIds plugin.id 子集，授权即自动启用
     * @return array{added: array, removed: array, changed: array}
     */
    public function sync(int $planId, array $pluginIds, array $autoEnableIds = []): array
    {
        // 校验 plugin_ids 都是已启用的有效插件
        $pluginIds = array_values(array_unique(array_map('intval', $pluginIds)));
        $autoEnableSet = array_fill_keys(array_map('intval', $autoEnableIds), true);

        $rows = [];
        $rejected = [];  // v2.6.2 issue #5：禁用/软删的 plugin_id 收集后一次性报错
        foreach ($pluginIds as $pid) {
            $plugin = $this->pluginRepo->find($pid);
            if (!$plugin) {
                throw new BusinessException("插件不存在: id={$pid}", 422);
            }
            // v2.6.2 issue #5：严格拒收禁用 / 软删插件，避免在 plugin_grants 留下死 grant
            // 后续权益查询虽然会过滤（v2.5.1 #1 已修），但 DB 里残留行会让平台超管
            // 误以为「已授权」。
            if ((int) $plugin['status'] !== Plugin::STATUS_ENABLED) {
                $rejected[] = "{$plugin['code']}(status={$plugin['status']})";
                continue;
            }
            if (!empty($plugin['deleted_at'])) {
                $rejected[] = "{$plugin['code']}(deleted)";
                continue;
            }
            $rows[] = [
                'plugin_id'   => $pid,
                'plugin_code' => (string) $plugin['code'],
                'auto_enable' => isset($autoEnableSet[$pid]) ? 1 : 0,
            ];
        }
        if ($rejected !== []) {
            throw new BusinessException(
                '以下插件无法授权（状态非已启用或已软删除）：' . implode(', ', $rejected),
                422,
            );
        }

        // v2.6.3 issue #1：diff 必须基于 raw grants，而不是按插件状态过滤后的结果。
        // listByPlan 过滤了 ENABLED + 未软删（v2.5.1 修复，供 EntitlementService 用），
        // 但 sync 的 diff 关心「DB 里物理存在的 grant 行」—— 已下架插件的旧 grant
        // 必须被 diff['removed'] 捕获，否则下游 reconcile 永远拆不掉残留菜单。
        $oldRows = array_map(fn ($r) => [
            'plugin_id'   => (int) $r['plugin_id'],
            'plugin_code' => (string) $r['plugin_code'],
            'auto_enable' => (int) $r['auto_enable'],
        ], $this->grantRepo->listRawByPlan($planId));

        $diff = self::diff($oldRows, $rows);

        $this->runInTransaction(function () use ($planId, $rows, $diff) {
            $this->grantRepo->replaceGrantsForPlan($planId, $rows);

            $tenantIds = null; // 懒加载：只有 added/removed 非空时才查

            // App 插件：只有 auto_enable=1 的新增授权才自动复制菜单。
            $addedPluginIds = array_column(
                array_filter($diff['added'], fn ($row) => (int) ($row['auto_enable'] ?? 0) === 1),
                'plugin_id'
            );
            if (!empty($addedPluginIds)) {
                $tenantIds = $tenantIds ?? $this->tenantRepository->listIdsByPlan($planId);

                foreach ($addedPluginIds as $pid) {
                    $plugin = $this->pluginRepo->find((int) $pid);
                    if (!$plugin || ($plugin['kind'] ?? Plugin::KIND_PLUGIN) !== Plugin::KIND_APP) {
                        continue;
                    }
                    foreach ($tenantIds as $tid) {
                        $this->appMenuInstaller->copyToTenant((int) $pid, (int) $tid);
                    }
                }
            }

            // App 插件：auto_enable 开关变化时同步租户菜单。
            if (!empty($diff['changed'])) {
                $tenantIds = $tenantIds ?? $this->tenantRepository->listIdsByPlan($planId);

                $now = date('Y-m-d H:i:s');
                foreach ($diff['changed'] as $row) {
                    $pid = (int) $row['plugin_id'];
                    $plugin = $this->pluginRepo->findWithTrashedById($pid);
                    if (!$plugin || ($plugin['kind'] ?? Plugin::KIND_PLUGIN) !== Plugin::KIND_APP) {
                        continue;
                    }

                    foreach ($tenantIds as $tid) {
                        if ((int) ($row['auto_enable'] ?? 0) === 1) {
                            $this->appMenuInstaller->copyToTenant($pid, (int) $tid);
                            continue;
                        }
                        if ($this->tenantHasActivePlugin((int) $tid, $pid, $now)) {
                            continue;
                        }
                        $this->appMenuInstaller->removeForTenant($pid, (int) $tid);
                    }
                }
            }

            // App 插件：取消授权时，从该套餐下每个租户拆掉菜单。
            // 例外：租户单独购买（tenant_plugins.source=PURCHASE）且仍在有效期内的不拆。
            $removedPluginIds = array_column($diff['removed'], 'plugin_id');
            if (!empty($removedPluginIds)) {
                $tenantIds = $tenantIds ?? $this->tenantRepository->listIdsByPlan($planId);

                $now = date('Y-m-d H:i:s');
                foreach ($removedPluginIds as $pid) {
                    // v2.6.4 issue #1：removed 包含已被全局禁用/软删但仍残留 grant 行
                    // 的 plugin_id；pluginRepo::find() 走 SoftDelete trait 会返回
                    // null，导致 kind=app 的残留菜单永远拆不掉。改用 findWithTrashedById
                    // 兜底，含软删行。
                    $plugin = $this->pluginRepo->findWithTrashedById((int) $pid);
                    if (!$plugin || ($plugin['kind'] ?? Plugin::KIND_PLUGIN) !== Plugin::KIND_APP) {
                        continue;
                    }
                    foreach ($tenantIds as $tid) {
                        if ($this->tenantOwnsViaPurchase((int) $tid, (int) $pid, $now)) {
                            continue;
                        }
                        $this->appMenuInstaller->removeForTenant((int) $pid, (int) $tid);
                    }
                }
            }
        });

        foreach ($this->tenantRepository->listIdsByPlan($planId) as $tenantId) {
            $this->entitlementService->flush((int) $tenantId);
        }

        return $diff;
    }

    /**
     * 拉取某套餐当前授权（含每行 auto_enable 与冗余 plugin_code）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByPlan(int $planId): array
    {
        return $this->grantRepo->listByPlan($planId);
    }

    /**
     * 判断租户是否通过单独购买持有该插件（仍在有效期内、状态 ACTIVE）。
     * 用于决定取消 plan 授权时是否保留该租户的菜单。
     *
     * v2.6.2 issue #4：JOIN plugins 过滤插件全局状态，避免「插件被禁用/软删后
     * 租户购买记录仍 active → 死菜单保留」。
     */
    private function tenantOwnsViaPurchase(int $tenantId, int $pluginId, string $now): bool
    {
        return $this->tenantHasActivePlugin($tenantId, $pluginId, $now, TenantPlugin::SOURCE_PURCHASE);
    }

    private function tenantHasActivePlugin(int $tenantId, int $pluginId, string $now, ?int $source = null): bool
    {
        return $this->tenantPluginRepo->hasActive($tenantId, $pluginId, $now, $source);
    }
}
