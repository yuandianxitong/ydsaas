<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\TenantPlugin;
use core\base\Repository;
use think\Model;

class TenantPluginRepository extends Repository
{
    protected function getModel(): Model
    {
        return new TenantPlugin();
    }

    /**
     * 列出某租户启用中的插件 code（用于运行期菜单/路由过滤）
     *
     * @return array<int, string>
     */
    public function listActiveCodes(int $tenantId): array
    {
        $now = date('Y-m-d H:i:s');
        return $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('status', TenantPlugin::STATUS_ACTIVE)
            ->where(function ($q) use ($now) {
                $q->whereNull('expired_at')->whereOr('expired_at', '>', $now);
            })
            ->column('plugin_code');
    }

    /**
     * 查某租户启用记录（含未启用/已禁用/过期；空表示从未碰过该插件）
     *
     * @return array<string, mixed>|null
     */
    public function findByTenantAndPlugin(int $tenantId, int $pluginId): ?array
    {
        $row = $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('plugin_id', $pluginId)
            ->find();
        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * 列出某租户的全部 tenant_plugins 行（任何 status，便于商城展示）
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByTenant(int $tenantId): array
    {
        return $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->order('id desc')
            ->select()
            ->toArray();
    }

    /**
     * 列出某租户已启用的插件（status=1），附带 plugins.entitlement + plugins.kind，
     * 供 EntitlementService 计算权益集合。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActive(int $tenantId): array
    {
        // v2.6.1：与 PluginGrantRepository 对称：插件在全局被禁用 / 软卸载后，
        // 即使租户单独购买记录仍 active，也不算作权益（避免下架插件继续生效）。
        return $this->getModel()->db()
            ->alias('tp')
            ->leftJoin('plugins p', 'tp.plugin_id = p.id')
            ->field('tp.id, tp.tenant_id, tp.plugin_id, tp.plugin_code, tp.status, tp.source, tp.expired_at, p.entitlement, p.kind')
            ->where('tp.tenant_id', $tenantId)
            ->where('tp.status', 1)
            ->where('p.status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->whereNull('p.deleted_at')
            ->select()
            ->toArray();
    }

    /**
     * 租户是否持有该插件的有效记录（记录 ACTIVE、插件全局启用、未过期）。
     *
     * JOIN plugins 过滤全局状态，避免下架插件的记录继续生效。
     *
     * @param int|null $source 限定来源（如 TenantPlugin::SOURCE_PURCHASE）；null 不限
     */
    public function hasActive(int $tenantId, int $pluginId, string $now, ?int $source = null): bool
    {
        $query = $this->getModel()->db()
            ->alias('tp')
            ->leftJoin('plugins p', 'tp.plugin_id = p.id')
            ->where('tp.tenant_id', $tenantId)
            ->where('tp.plugin_id', $pluginId)
            ->where('tp.status', TenantPlugin::STATUS_ACTIVE)
            ->where('p.status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->whereNull('p.deleted_at')
            ->field('tp.expired_at');
        if ($source !== null) {
            $query->where('tp.source', $source);
        }

        $row = $query->find();
        if (!$row) {
            return false;
        }
        $expired = $row['expired_at'] ?? null;
        return $expired === null || $expired === '' || $expired > $now;
    }

    /**
     * 租户是否通过单独购买持有该插件（hasActive 的 SOURCE_PURCHASE 特化）。
     */
    public function hasActivePurchase(int $tenantId, int $pluginId, string $now): bool
    {
        return $this->hasActive($tenantId, $pluginId, $now, TenantPlugin::SOURCE_PURCHASE);
    }

    /**
     * 原子占位演示数据导入：仅当行 ACTIVE 且尚未导入时写入时间戳。
     * 返回 true = 本请求抢到导入权（并发双击/重试下最多一个成功）。
     */
    public function claimTestdataImport(int $tenantId, int $pluginId, string $now): bool
    {
        return $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('plugin_id', $pluginId)
            ->where('status', TenantPlugin::STATUS_ACTIVE)
            ->whereNull('testdata_imported_at')
            ->update(['testdata_imported_at' => $now, 'updated_at' => $now]) === 1;
    }

    /** 引擎导入失败时释放占位，保持可重试 */
    public function releaseTestdataImport(int $tenantId, int $pluginId): void
    {
        $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('plugin_id', $pluginId)
            ->update(['testdata_imported_at' => null]);
    }
}
