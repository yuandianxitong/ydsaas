<?php
/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use app\model\plugin\Plugin;
use app\model\plugin\TenantPlugin;
use core\plugin\AppMenuInstaller;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * saas:plugin-menu-reconcile
 *
 * 把每个租户实际持有的 app 插件菜单，对齐到「plan 授权 ∪ 单独购买且 ACTIVE」的期望集合。
 *
 *   - 多出来的（plan 不授权也没买） → removeForTenant
 *   - 少了的（plan 授权但菜单没在） → copyToTenant
 *   - 命中 tenant_id=0 / 已软删租户不处理（前者是模板，后者按需可由调用方另行清理）
 *
 * 选项：
 *   --tenant=ID   只处理指定租户
 *   --dry-run     只打印动作不落库
 *
 * 幂等。
 */
class SaasPluginMenuReconcile extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:plugin-menu-reconcile')
            ->setDescription('Reconcile per-tenant app plugin menus against plan grants + purchases')
            ->addOption('tenant', null, Option::VALUE_OPTIONAL, '只处理某个 tenant_id', null)
            ->addOption('dry-run', null, Option::VALUE_NONE, '只打印动作不落库');
    }

    protected function execute(Input $input, Output $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $onlyTenant = $input->getOption('tenant');
        $onlyTenant = $onlyTenant !== null ? (int) $onlyTenant : null;

        // 1. 加载所有已启用的 app 插件（id ↔ code）
        $apps = Db::table('plugins')
            ->where('status', Plugin::STATUS_ENABLED)
            ->where('kind', Plugin::KIND_APP)
            ->whereNull('deleted_at')
            ->column('code', 'id');
        if (empty($apps)) {
            $output->writeln('No active app plugins. Nothing to reconcile.');
            return 0;
        }

        // 2. 模板 menu_id → plugin_id（用来判断哪些菜单是「插件菜单」）
        $templateMenuToPlugin = Db::table('plugin_menus')->column('plugin_id', 'menu_id');
        if (empty($templateMenuToPlugin)) {
            $output->writeln('No plugin menu templates. Nothing to reconcile.');
            return 0;
        }
        $templateCodes = Db::table('menus')
            ->where('tenant_id', 0)
            ->whereIn('id', array_keys($templateMenuToPlugin))
            ->column('code', 'id');
        // menu_code → plugin_id
        $codeToPlugin = [];
        foreach ($templateCodes as $tplId => $code) {
            $codeToPlugin[(string) $code] = (int) $templateMenuToPlugin[(int) $tplId];
        }

        // 3. 遍历租户
        $query = Db::table('tenants')->whereNull('deleted_at')->order('id', 'asc');
        if ($onlyTenant !== null) {
            $query->where('id', $onlyTenant);
        }
        $tenants = $query->select()->toArray();

        $installer = app(AppMenuInstaller::class);
        $totalRemoved = $totalAdded = 0;

        foreach ($tenants as $t) {
            $tenantId = (int) $t['id'];
            $planId = (int) ($t['plan_id'] ?? 0);

            $expected = $this->expectedPluginIds($tenantId, $planId);

            // 当前持有：扫描该租户菜单，按 code 反查 plugin_id
            $heldCodes = Db::table('menus')
                ->where('tenant_id', $tenantId)
                ->whereIn('code', array_keys($codeToPlugin))
                ->whereNull('deleted_at')
                ->column('code');
            $heldPluginIds = [];
            foreach ($heldCodes as $code) {
                $pid = $codeToPlugin[(string) $code] ?? null;
                if ($pid !== null) {
                    $heldPluginIds[$pid] = true;
                }
            }
            $heldPluginIds = array_keys($heldPluginIds);

            $toRemove = array_diff($heldPluginIds, $expected);
            $toAdd    = array_diff($expected, $heldPluginIds);

            if (empty($toRemove) && empty($toAdd)) {
                continue;
            }

            $output->writeln(sprintf(
                '[tenant=%d code=%s plan=%d] remove=%s add=%s',
                $tenantId,
                (string) ($t['tenant_code'] ?? ''),
                $planId,
                $this->formatPluginList($toRemove, $apps),
                $this->formatPluginList($toAdd, $apps)
            ));

            if ($dryRun) {
                continue;
            }

            foreach ($toRemove as $pid) {
                $installer->removeForTenant((int) $pid, $tenantId);
                $totalRemoved++;
            }
            foreach ($toAdd as $pid) {
                $installer->copyToTenant((int) $pid, $tenantId);
                $totalAdded++;
            }
        }

        // 4. 孤儿菜单（菜单的 tenant_id 在 tenants 表已经不存在）：单独扫一次
        $orphan = $this->cleanupOrphanTenantData($apps, $codeToPlugin, $installer, $output, $dryRun);

        $output->writeln(sprintf(
            '%sReconcile done: removed=%d, added=%d, orphan_cleared=%d',
            $dryRun ? '[DRY-RUN] ' : '',
            $totalRemoved,
            $totalAdded,
            $orphan
        ));
        return 0;
    }

    /**
     * 该租户「应该有」哪些 app 插件菜单：plan 授权的 + 单独购买且 ACTIVE 未过期的。
     *
     * @return array<int, int>
     */
    private function expectedPluginIds(int $tenantId, int $planId): array
    {
        $ids = [];
        if ($planId > 0) {
            $granted = Db::table('plugin_grants')
                ->alias('pg')
                ->join('plugins p', 'p.id = pg.plugin_id')
                ->where('pg.plan_id', $planId)
                ->where('p.status', Plugin::STATUS_ENABLED)
                ->where('p.kind', Plugin::KIND_APP)
                ->whereNull('p.deleted_at')
                ->column('p.id');
            foreach ($granted as $pid) {
                $ids[(int) $pid] = true;
            }
        }

        // v2.6.3 issue #2：购买分支与 plan 分支对齐，过滤掉「插件全局已禁用 /
        // 软删」的行，避免下架插件还被 reconcile 当成「应有菜单」。
        // 与 TenantService / PluginGrantService::tenantOwnsViaPurchase 保持一致。
        $now = date('Y-m-d H:i:s');
        $purchased = Db::table('tenant_plugins')
            ->alias('tp')
            ->join('plugins p', 'p.id = tp.plugin_id')
            ->where('tp.tenant_id', $tenantId)
            ->where('tp.source', TenantPlugin::SOURCE_PURCHASE)
            ->where('tp.status', TenantPlugin::STATUS_ACTIVE)
            ->where('p.status', Plugin::STATUS_ENABLED)
            ->whereNull('p.deleted_at')
            ->where('p.kind', Plugin::KIND_APP)
            ->where(function ($q) use ($now) {
                $q->whereNull('tp.expired_at')->whereOr('tp.expired_at', '>', $now);
            })
            ->column('p.id');
        foreach ($purchased as $pid) {
            $ids[(int) $pid] = true;
        }
        return array_keys($ids);
    }

    /**
     * 扫菜单表里 tenant_id > 0 但已不在 tenants 表里的孤儿数据，连菜单+role_menus 一并删。
     */
    private function cleanupOrphanTenantData(
        array $apps,
        array $codeToPlugin,
        AppMenuInstaller $installer,
        Output $output,
        bool $dryRun
    ): int {
        $liveTenantIds = array_map('intval', Db::table('tenants')->whereNull('deleted_at')->column('id'));
        $liveSet = array_fill_keys($liveTenantIds, true);

        $orphanRows = Db::table('menus')
            ->where('tenant_id', '>', 0)
            ->whereIn('code', array_keys($codeToPlugin))
            ->whereNull('deleted_at')
            ->field('tenant_id, code')
            ->select()
            ->toArray();

        $byTenant = [];
        foreach ($orphanRows as $r) {
            $tid = (int) $r['tenant_id'];
            if (isset($liveSet[$tid])) {
                continue;
            }
            $pid = $codeToPlugin[(string) $r['code']] ?? null;
            if ($pid === null) {
                continue;
            }
            $byTenant[$tid][$pid] = true;
        }

        $cleared = 0;
        foreach ($byTenant as $tid => $pluginSet) {
            $pids = array_keys($pluginSet);
            $output->writeln(sprintf(
                '[orphan tenant_id=%d] remove %s',
                $tid,
                $this->formatPluginList($pids, $apps)
            ));
            if ($dryRun) {
                continue;
            }
            foreach ($pids as $pid) {
                $installer->removeForTenant((int) $pid, (int) $tid);
                $cleared++;
            }
        }
        return $cleared;
    }

    /**
     * @param array<int, int> $pluginIds
     * @param array<int, string> $apps
     */
    private function formatPluginList(array $pluginIds, array $apps): string
    {
        if (empty($pluginIds)) {
            return '[]';
        }
        $names = array_map(fn ($id) => $apps[(int) $id] ?? "#{$id}", $pluginIds);
        return '[' . implode(',', $names) . ']';
    }
}
