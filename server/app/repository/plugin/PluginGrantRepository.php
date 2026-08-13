<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\PluginGrant;
use core\base\Repository;
use think\Model;

class PluginGrantRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PluginGrant();
    }

    /**
     * 列出某套餐的全部授权（附带 plugins.entitlement + plugins.kind，供 EntitlementService 使用）
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByPlan(int $planId): array
    {
        // p.status=ENABLED 与 p.deleted_at IS NULL：插件在全局被禁用 / 软卸载后，
        // 套餐里的历史授权记录不再算作租户权益，避免 EntitlementService 把已下架
        // 的能力下发给租户。
        return $this->getModel()->db()
            ->alias('g')
            ->leftJoin('plugins p', 'g.plugin_id = p.id')
            ->field('g.id, g.plan_id, g.plugin_id, g.plugin_code, g.auto_enable, g.created_at, p.entitlement, p.kind')
            ->where('g.plan_id', $planId)
            ->where('p.status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->whereNull('p.deleted_at')
            ->select()
            ->toArray();
    }

    /**
     * 列出某套餐的全部授权（listByPlan 的语义别名，供新 EntitlementService 调用）
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForPlan(int $planId): array
    {
        return $this->listByPlan($planId);
    }

    /**
     * 列出某套餐的 **原始** 授权（不按插件状态过滤）。
     *
     * v2.6.3 新增：listByPlan() 过滤 ENABLED + 未软删后，sync() 的 diff
     * 看不到「插件已下架但 plugin_grants 行还在」的 grant，导致租户菜单残留。
     * 本方法专供 sync.diff / reconcile 等需要看到「物理 grant 行」的场景；
     * 权益判断与租户展示继续用 listByPlan()。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRawByPlan(int $planId): array
    {
        return $this->getModel()->db()
            ->alias('g')
            ->leftJoin('plugins p', 'g.plugin_id = p.id')
            ->field('g.id, g.plan_id, g.plugin_id, g.plugin_code, g.auto_enable, g.created_at, p.entitlement, p.kind')
            ->where('g.plan_id', $planId)
            ->select()
            ->toArray();
    }

    /**
     * 列出某插件被授权到的全部套餐
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByPlugin(int $pluginId): array
    {
        return $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->select()
            ->toArray();
    }

    /**
     * 列出已被授权该插件的所有租户 id（通过 plan_id 关联 tenants 表，排除软删）。
     *
     * @return array<int, int>
     */
    public function listGrantedTenantIds(int $pluginId): array
    {
        $planIds = $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->column('plan_id');
        if ($planIds === []) {
            return [];
        }
        return array_map('intval', (new \app\model\saas\Tenant())->db()
            ->whereIn('plan_id', $planIds)
            ->whereNull('deleted_at')
            ->column('id'));
    }

    /**
     * 用新一批授权关系完全替换某套餐的所有授权（事务由调用方管理）。
     *
     * @param int $planId
     * @param array<int, array{plugin_id: int, plugin_code: string, auto_enable: int}> $rows
     */
    public function replaceGrantsForPlan(int $planId, array $rows): void
    {
        $model = $this->getModel();
        $now = date('Y-m-d H:i:s');

        // 1. 删旧
        $model->db()->where('plan_id', $planId)->delete();

        // 2. 批量插新
        if ($rows === []) {
            return;
        }
        $insertRows = array_map(fn ($row) => array_merge($row, [
            'plan_id'    => $planId,
            'created_at' => $now,
        ]), $rows);
        $model->db()->insertAll($insertRows);
    }

    /**
     * 列出某套餐授权的全部插件，附带 plugin 表的展示字段（code/name/icon/version/description）。
     * 用于租户端"商城/可用列表"展示。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByPlanForTenant(int $planId): array
    {
        // 返回 app + plugin 两类（前端按 kind 分组展示），不在此处按 kind 过滤
        // 与 listByPlan 对齐：插件全局禁用 / 软卸载后不在租户视图里展示
        return $this->getModel()->db()->alias('g')
            ->leftJoin('plugins p', 'p.id = g.plugin_id')
            ->where('g.plan_id', $planId)
            ->where('p.status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->whereNull('p.deleted_at')
            ->field([
                'g.id'          => 'grant_id',
                'g.plan_id',
                'g.plugin_id',
                'g.plugin_code',
                'g.auto_enable',
                'p.name'        => 'plugin_name',
                'p.version'     => 'plugin_version',
                'p.icon'        => 'plugin_icon',
                'p.description' => 'plugin_description',
                'p.type'        => 'plugin_type',
                'p.source'      => 'plugin_source',
                'p.status'      => 'plugin_status',
                'p.kind'        => 'plugin_kind',
                'p.manifest'    => 'plugin_manifest',
            ])
            // p.kind 'app' < 'plugin' 字典序，desc 让 app 排前
            ->order('p.kind desc, g.id asc')
            ->select()
            ->toArray();
    }

    /**
     * 某套餐授权的 kind=app 且 status=ENABLED 的插件 ID 列表（用于套餐切换时的菜单同步）。
     *
     * @return array<int, int>
     */
    public function appPluginIdsByPlan(int $planId): array
    {
        if ($planId <= 0) {
            return [];
        }
        return array_map('intval', $this->getModel()->db()
            ->alias('pg')
            ->join('plugins p', 'p.id = pg.plugin_id')
            ->where('pg.plan_id', $planId)
            ->where('p.status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->where('p.kind', \app\model\plugin\Plugin::KIND_APP)
            ->whereNull('p.deleted_at')
            ->column('p.id'));
    }
}
