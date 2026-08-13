<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use app\model\plugin\TenantPlugin;
use app\repository\plugin\PluginGrantRepository;
use app\repository\plugin\PluginRepository;
use app\repository\plugin\TenantPluginRepository;
use app\repository\saas\TenantRepository;
use core\base\Service;
use core\entitlement\Entitlement;

/**
 * 计算租户当前可用权益（plugin_grants ∪ tenant_plugins → Entitlement[]）。
 *
 * - source=plan：套餐 grants 自动下发的权益。
 * - source=purchase：单租户单独启用 / 购买的权益（覆盖同 code 的 plan）。
 * - 进程级缓存：每个 PHP 请求生命周期内不重复计算。
 */
final class EntitlementService extends Service
{
    protected PluginGrantRepository $grantRepository;
    protected TenantPluginRepository $tenantPluginRepository;
    protected TenantRepository $tenantRepository;
    protected PluginRepository $pluginRepository;

    /** @var array<int, Entitlement[]> tenantId → entitlements */
    private array $cache = [];

    /** @return Entitlement[] */
    public function list(int $tenantId): array
    {
        if (isset($this->cache[$tenantId])) {
            return $this->cache[$tenantId];
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        if ($tenant === null) {
            return $this->cache[$tenantId] = [];
        }

        $byCode = [];

        $tenantPluginRows = $this->tenantPluginRepository->listByTenant($tenantId);
        $disabledPluginIds = [];
        foreach ($tenantPluginRows as $tp) {
            if ((int) ($tp['status'] ?? 0) === TenantPlugin::STATUS_DISABLED) {
                $disabledPluginIds[(int) ($tp['plugin_id'] ?? 0)] = true;
            }
        }

        $grants = $this->grantRepository->listForPlan((int) ($tenant['plan_id'] ?? 0));
        $grantedPluginIds = [];
        foreach ($grants as $g) {
            $grantedPluginIds[(int) ($g['plugin_id'] ?? 0)] = true;
        }

        foreach ($grants as $g) {
            if ((int) ($g['auto_enable'] ?? 0) !== 1) {
                continue;
            }
            if (isset($disabledPluginIds[(int) ($g['plugin_id'] ?? 0)])) {
                continue;
            }
            $code = (string) ($g['entitlement'] ?? '');
            if ($code === '') {
                continue;
            }
            $byCode[$code] = new Entitlement(
                code: $code,
                kind: (string) ($g['kind'] ?? 'plugin'),
                source: 'plan',
                expiresAt: $tenant['expires_at'] ?? null,
            );
        }

        $now = time();
        foreach ($this->tenantPluginRepository->listActive($tenantId) as $tp) {
            if (!empty($tp['expired_at']) && strtotime((string) $tp['expired_at']) < $now) {
                continue;
            }
            $source = (int) ($tp['source'] ?? 0);
            if ($source === TenantPlugin::SOURCE_PLAN && !isset($grantedPluginIds[(int) ($tp['plugin_id'] ?? 0)])) {
                continue;
            }
            $code = (string) ($tp['entitlement'] ?? '');
            if ($code === '') {
                continue;
            }
            $byCode[$code] = new Entitlement(
                code: $code,
                kind: (string) ($tp['kind'] ?? 'plugin'),
                source: $source === TenantPlugin::SOURCE_PLAN ? 'plan' : 'purchase',
                expiresAt: $tp['expired_at'] ?? null,
            );
        }

        return $this->cache[$tenantId] = array_values($byCode);
    }

    public function has(int $tenantId, string $code): bool
    {
        foreach ($this->list($tenantId) as $e) {
            if ($e->code === $code) {
                return true;
            }
        }
        return false;
    }

    public function hasAny(int $tenantId, array $codes): bool
    {
        foreach ($this->list($tenantId) as $e) {
            if (in_array($e->code, $codes, true)) {
                return true;
            }
        }
        return false;
    }

    public function flush(int $tenantId): void
    {
        unset($this->cache[$tenantId]);
    }

    public function flushAll(): void
    {
        $this->cache = [];
    }

    /**
     * 供前端展示：在 toArray() 基础上补 name / icon（不改 VO / 门控契约）。
     *
     * @return array<int, array{code:string,kind:string,source:string,expires_at:?string,name:string,icon:string}>
     */
    public function listForDisplay(int $tenantId): array
    {
        $rows = array_map(
            static fn (Entitlement $e) => $e->toArray(),
            $this->list($tenantId)
        );

        return $this->enrichDisplay($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function enrichDisplay(array $rows): array
    {
        $codes = [];
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        $meta = $this->pluginRepository->displayMetaByEntitlementCodes($codes);

        foreach ($rows as &$row) {
            $code = (string) ($row['code'] ?? '');
            $hit = $meta[$code] ?? null;
            $row['name'] = (string) ($hit['name'] ?? ($code !== '' ? $code : ''));
            $row['icon'] = (string) ($hit['icon'] ?? '');
        }
        unset($row);

        return $rows;
    }
}
