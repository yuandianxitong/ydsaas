<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\repository\plugin\PluginRepository;
use app\repository\plugin\TenantPluginConfigRepository;
use app\service\saas\EntitlementService;
use core\base\Service;
use core\exception\BusinessException;

/**
 * 租户插件配置（K-V）读写。
 */
class TenantPluginConfigService extends Service
{
    protected TenantPluginConfigRepository $configRepo;
    protected EntitlementService $entitlementService;
    protected PluginRepository $pluginRepository;

    /**
     * @return array<string, string|null>
     */
    public function getConfig(int $tenantId, string $pluginCode): array
    {
        // v2.6.2 issue #3：读写都用 EntitlementService.has 作为唯一权限门。
        // listByTenant() 包含历史启用过但已禁用/过期/全局下架的行，不能作为
        // 「可读」标准。EntitlementService 已包含 plugins.status=ENABLED +
        // deleted_at IS NULL + 单独购买未过期 的完整逻辑。
        $this->assertEntitled($tenantId, $pluginCode);
        return $this->configRepo->loadConfigs($tenantId, $pluginCode);
    }

    /**
     * @param array<string, mixed> $kv 任意 K-V，写入时统一字符串化
     * @return array<string, string|null>
     */
    public function updateConfig(int $tenantId, string $pluginCode, array $kv): array
    {
        $this->assertEntitled($tenantId, $pluginCode);

        $normalized = [];
        foreach ($kv as $k => $v) {
            $normalized[(string) $k] = $v === null ? null : (string) $v;
        }

        $this->runInTransaction(function () use ($tenantId, $pluginCode, $normalized) {
            $this->configRepo->upsert($tenantId, $pluginCode, $normalized);
        });

        return $normalized;
    }

    /**
     * v2.6.5 issue #1：配置服务参数始终是 plugin code（与 configSchema 一致）；
     * 权益校验用 plugin.entitlement —— 兼容 entitlement != code 的插件。
     * 配置表存取仍按 pluginCode（plugin_configs 行的索引列）。
     */
    private function assertEntitled(int $tenantId, string $pluginCode): void
    {
        $plugin = $this->pluginRepository->findByCode($pluginCode);
        if (!$plugin) {
            throw new BusinessException("插件不存在: {$pluginCode}", 404);
        }
        $entitlement = (string) ($plugin['entitlement'] ?? $plugin['code'] ?? '');
        if (!$this->entitlementService->has($tenantId, $entitlement)) {
            throw new BusinessException('当前无该插件权益，无法读写其配置', 403);
        }
    }
}
