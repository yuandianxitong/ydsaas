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
use core\plugin\contracts\LifecycleHook;
use core\tenant\TenantContext;
use think\facade\Db;

/**
 * 租户级插件管理：启用 / 禁用 / 列表 / 详情。
 *
 * 边界：
 *   - 启用前必须：plugin.status=ENABLED + 套餐授权该 plugin
 *   - 启用后写 tenant_plugins(status=1, installed_version=plugin.version) + 调 LifecycleHook.enable
 *   - 禁用：tenant_plugins.status=2 + LifecycleHook.disable，不删配置（保留以便重启用）
 */
class TenantPluginService extends Service
{
    protected TenantPluginRepository $tenantPluginRepo;
    protected PluginRepository $pluginRepo;
    protected PluginGrantRepository $grantRepo;
    protected \app\service\saas\EntitlementService $entitlementService;
    protected AppMenuInstaller $appMenuInstaller;
    protected \core\plugin\PluginSqlRunner $sqlRunner;

    /**
     * 列出当前租户"可见"的所有插件（套餐授权 + 历史启用过的）。
     * 包含状态字段供前端展示按钮。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAvailable(int $tenantId, int $planId): array
    {
        $grants = $this->grantRepo->listByPlanForTenant($planId);
        $myPluginRows = $this->tenantPluginRepo->listByTenant($tenantId);
        $myMap = [];
        foreach ($myPluginRows as $row) {
            $myMap[(int) $row['plugin_id']] = $row;
        }

        $result = [];
        foreach ($grants as $grant) {
            $pluginId = (int) $grant['plugin_id'];
            $mine = $myMap[$pluginId] ?? null;
            $manifest = $grant['plugin_manifest'] ?? null;
            if (is_string($manifest)) {
                $decoded = json_decode($manifest, true);
                $manifest = is_array($decoded) ? $decoded : null;
            }
            $result[] = [
                'plugin_id'         => $pluginId,
                'plugin_code'       => (string) $grant['plugin_code'],
                'name'              => (string) $grant['plugin_name'],
                'version'           => (string) $grant['plugin_version'],
                // v2.7.4：把 manifest 原值（如 "icon.png"）转成浏览器可拉 URL；空保留空 → 前端兜底
                'icon'              => \core\plugin\PluginIconResolver::iconUrl(
                    (string) $grant['plugin_code'],
                    (string) ($grant['plugin_icon'] ?? '')
                ),
                'description'       => (string) ($grant['plugin_description'] ?? ''),
                'type'              => (int) $grant['plugin_type'],
                'source'            => (int) $grant['plugin_source'],
                'kind'              => (string) ($grant['plugin_kind'] ?? 'plugin'),
                'category'          => \core\plugin\PluginCategory::normalize(
                    is_array($manifest) ? ($manifest['category'] ?? null) : null
                ),
                'auto_enable'       => (int) $grant['auto_enable'],
                'tenant_status'     => $mine ? (int) $mine['status'] : TenantPlugin::STATUS_INACTIVE,
                'installed_version' => $mine ? (string) $mine['installed_version'] : '',
                'enabled_at'        => $mine ? $mine['enabled_at'] : null,
                'expired_at'        => $mine ? $mine['expired_at'] : null,
                // v2.28.0：仅已安装插件才有意义——是否带 database/testdata.sql + 是否已导入过
                'has_testdata'      => $mine
                    ? $this->sqlRunner->hasTestdata(\think\facade\App::getRootPath() . 'plugins/' . (string) $grant['plugin_code'])
                    : false,
                'testdata_imported_at' => $mine['testdata_imported_at'] ?? null,
                // 详情页字段：作者、能力清单（manifest.permissions）
                'author'            => (string) ($manifest['author'] ?? ''),
                'capabilities'      => array_values(array_map(
                    fn ($p) => ['code' => (string) ($p['code'] ?? ''), 'name' => (string) ($p['name'] ?? '')],
                    is_array($manifest['tenant']['permissions'] ?? null) ? $manifest['tenant']['permissions'] : []
                )),
                'panels'            => array_values(array_map(
                    fn ($p) => [
                        'code'      => (string) ($p['code'] ?? ''),
                        'name'      => (string) ($p['name'] ?? ''),
                        'component' => (string) ($p['component'] ?? ($p['code'] ?? '')),
                        'icon'      => (string) ($p['icon'] ?? ''),
                    ],
                    is_array($manifest['tenant']['panels'] ?? null) ? $manifest['tenant']['panels'] : []
                )),
            ];
        }
        return $result;
    }

    public function enable(int $tenantId, int $pluginId): array
    {
        // 校验 1: 插件全局已启用
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException('插件不存在', 404);
        }
        if ((int) $plugin['status'] !== Plugin::STATUS_ENABLED) {
            throw new BusinessException('插件当前状态不可启用', 409);
        }

        // 校验 2: 套餐授权了该插件
        $planId = $this->currentPlanId($tenantId);
        if ($planId <= 0) {
            throw new BusinessException('租户未绑定有效套餐', 403);
        }
        $grants = $this->grantRepo->listByPlan($planId);
        $authorized = false;
        foreach ($grants as $g) {
            if ((int) $g['plugin_id'] === $pluginId) {
                $authorized = true;
                break;
            }
        }
        if (!$authorized) {
            throw new BusinessException('当前套餐未授权此插件，请联系平台升级套餐', 403);
        }

        $manifest = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);

        $now = date('Y-m-d H:i:s');
        $code = (string) $plugin['code'];
        $existing = $this->tenantPluginRepo->findByTenantAndPlugin($tenantId, $pluginId);

        $this->runInTransaction(function () use ($tenantId, $pluginId, $code, $plugin, $existing, $now) {
            $payload = [
                'tenant_id'         => $tenantId,
                'plugin_id'         => $pluginId,
                'plugin_code'       => $code,
                'status'            => TenantPlugin::STATUS_ACTIVE,
                'source'            => TenantPlugin::SOURCE_PLAN,
                'enabled_at'        => $now,
                'installed_version' => (string) $plugin['version'],
                'updated_at'        => $now,
            ];
            if ($existing) {
                $this->tenantPluginRepo->update((int) $existing['id'], $payload);
            } else {
                $payload['created_at'] = $now;
                $this->tenantPluginRepo->create($payload);
            }

            if (($plugin['kind'] ?? Plugin::KIND_PLUGIN) === Plugin::KIND_APP) {
                $this->appMenuInstaller->copyToTenant($pluginId, $tenantId);
            }
        });

        $this->invokeLifecycle($manifest, 'enable', $tenantId);

        $this->entitlementService->flush($tenantId);

        return ['plugin_id' => $pluginId, 'plugin_code' => $code, 'status' => TenantPlugin::STATUS_ACTIVE];
    }

    public function disable(int $tenantId, int $pluginId): array
    {
        $existing = $this->tenantPluginRepo->findByTenantAndPlugin($tenantId, $pluginId);
        if (!$existing) {
            throw new BusinessException('未启用此插件，无需禁用', 404);
        }
        if ((int) $existing['status'] !== TenantPlugin::STATUS_ACTIVE) {
            throw new BusinessException('插件当前未启用', 409);
        }

        $plugin = $this->pluginRepo->find($pluginId);
        $manifest = $plugin
            ? (is_array($plugin['manifest'] ?? null)
                ? $plugin['manifest']
                : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []))
            : [];

        $now = date('Y-m-d H:i:s');
        $this->runInTransaction(function () use ($existing, $plugin, $pluginId, $tenantId, $now) {
            $this->tenantPluginRepo->update((int) $existing['id'], [
                'status'     => TenantPlugin::STATUS_DISABLED,
                'updated_at' => $now,
            ]);

            if ($plugin && ($plugin['kind'] ?? Plugin::KIND_PLUGIN) === Plugin::KIND_APP) {
                $this->appMenuInstaller->removeForTenant($pluginId, $tenantId);
            }
        });

        // 禁用 hook 抛错不阻断
        try {
            $this->invokeLifecycle($manifest, 'disable', $tenantId);
        } catch (\Throwable $e) {
            error_log("[plugin:{$existing['plugin_code']}] disable hook failed: " . $e->getMessage());
        }

        $this->entitlementService->flush($tenantId);

        return ['plugin_id' => $pluginId, 'status' => TenantPlugin::STATUS_DISABLED];
    }

    /**
     * 激活单独购买的插件：tenant_plugins.source=PURCHASE, expired_at=now+months。
     *
     * 与 enable() 不同：
     *   - 不校验套餐授权（用户已花钱购买）
     *   - 自动写过期时间（叠加未过期的现有 expired_at）
     *   - 仍调 Lifecycle.enable hook
     *
     * @return array<string, mixed>
     */
    public function activatePurchased(int $tenantId, int $pluginId, int $months): array
    {
        if ($months <= 0) {
            throw new BusinessException('购买月数必须 > 0', 422);
        }
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException('插件不存在', 404);
        }
        if ((int) $plugin['status'] !== Plugin::STATUS_ENABLED) {
            throw new BusinessException('插件当前状态不可激活', 409);
        }

        $code = (string) $plugin['code'];
        $manifest = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);

        $now = date('Y-m-d H:i:s');
        $existing = $this->tenantPluginRepo->findByTenantAndPlugin($tenantId, $pluginId);
        $newExpired = $this->extendExpiration($existing['expired_at'] ?? null, $months, $now);

        $this->runInTransaction(function () use ($tenantId, $pluginId, $code, $plugin, $existing, $newExpired, $now) {
            $payload = [
                'tenant_id'         => $tenantId,
                'plugin_id'         => $pluginId,
                'plugin_code'       => $code,
                'status'            => TenantPlugin::STATUS_ACTIVE,
                'source'            => TenantPlugin::SOURCE_PURCHASE,
                'enabled_at'        => $now,
                'expired_at'        => $newExpired,
                'installed_version' => (string) $plugin['version'],
                'updated_at'        => $now,
            ];
            if ($existing) {
                $this->tenantPluginRepo->update((int) $existing['id'], $payload);
            } else {
                $payload['created_at'] = $now;
                $this->tenantPluginRepo->create($payload);
            }
        });

        $this->invokeLifecycle($manifest, 'enable', $tenantId);

        $this->entitlementService->flush($tenantId);

        return ['plugin_id' => $pluginId, 'plugin_code' => $code, 'expired_at' => $newExpired];
    }

    /**
     * 导入插件演示数据（每租户一次；插件须已启用且包内含 testdata.sql）。
     *
     * @return array{imported: int, imported_at: string}
     */
    public function importTestdata(int $tenantId, int $pluginId): array
    {
        $tp = $this->tenantPluginRepo->findByTenantAndPlugin($tenantId, $pluginId);
        if (!$tp || (int) $tp['status'] !== TenantPlugin::STATUS_ACTIVE) {
            throw new BusinessException('插件未启用，无法导入演示数据', 409);
        }
        if (!empty($tp['testdata_imported_at'])) {
            throw new BusinessException('演示数据已导入过，不可重复导入', 409);
        }

        // 原子占位：仅当行仍是 ACTIVE 且尚未导入时才能抢到写权限，覆盖并发双击/重试窗口。
        $now = date('Y-m-d H:i:s');
        if (!$this->tenantPluginRepo->claimTestdataImport($tenantId, $pluginId, $now)) {
            throw new BusinessException('演示数据已导入过，不可重复导入', 409);
        }

        $pluginDir = \think\facade\App::getRootPath() . 'plugins/' . $tp['plugin_code'];
        try {
            $count = $this->sqlRunner->importTestdata($pluginDir, $tenantId);
        } catch (\Throwable $e) {
            $this->tenantPluginRepo->releaseTestdataImport($tenantId, $pluginId);
            throw $e;
        }

        return ['imported' => $count, 'imported_at' => $now];
    }

    /**
     * 把 expired_at 在原基础上叠加 $months（如已过期或为空，从 now 起算）。
     */
    private function extendExpiration(?string $current, int $months, string $now): string
    {
        $base = ($current !== null && $current !== '' && $current > $now) ? $current : $now;
        return date('Y-m-d H:i:s', strtotime("+{$months} months", strtotime($base)));
    }

    /**
     * 合并 plan_grants (auto_enable) + tenant_plugins (status=ACTIVE & not expired) 的 plugin_code 集合。
     * 纯函数；TenantFeatureService 也用它。
     *
     * @param array<int, array<string, mixed>> $grants
     * @param array<int, array<string, mixed>> $tenantPlugins
     * @return array<int, string>
     */
    public static function mergeActiveCodes(array $grants, array $tenantPlugins, string $now): array
    {
        $codes = [];
        foreach ($grants as $g) {
            if ((int) ($g['auto_enable'] ?? 0) === 1 && !empty($g['plugin_code'])) {
                $codes[(string) $g['plugin_code']] = true;
            }
        }
        foreach ($tenantPlugins as $tp) {
            if ((int) ($tp['status'] ?? 0) !== TenantPlugin::STATUS_ACTIVE) {
                continue;
            }
            $expired = $tp['expired_at'] ?? null;
            if ($expired !== null && $expired !== '' && $expired <= $now) {
                continue;
            }
            if (!empty($tp['plugin_code'])) {
                $codes[(string) $tp['plugin_code']] = true;
            }
        }
        return array_keys($codes);
    }

    /**
     * 拿当前租户的 plan_id（先从 TenantContext，再回退 tenants 表）。
     */
    private function currentPlanId(int $tenantId): int
    {
        $ctx = TenantContext::current();
        if ($ctx !== null) {
            $data = $ctx->tenantData();
            if (!empty($data['plan_id'])) {
                return (int) $data['plan_id'];
            }
        }
        return (int) Db::table('tenants')->where('id', $tenantId)->value('plan_id');
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function invokeLifecycle(array $manifest, string $hook, int $tenantId): void
    {
        $lifecycleClass = (string) ($manifest['lifecycle'] ?? '');
        if ($lifecycleClass === '' || !class_exists($lifecycleClass)) {
            return;
        }
        $instance = new $lifecycleClass();
        if (!$instance instanceof LifecycleHook) {
            throw BusinessException::pluginRuntimeError(
                (string) ($manifest['code'] ?? '?'),
                $hook,
                new \RuntimeException('lifecycle 类未实现 LifecycleHook 接口')
            );
        }
        try {
            $instance->{$hook}($tenantId);
        } catch (\Throwable $e) {
            throw BusinessException::pluginRuntimeError(
                (string) ($manifest['code'] ?? '?'),
                $hook,
                $e
            );
        }
    }
}
