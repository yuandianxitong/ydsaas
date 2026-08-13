<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Config;

/**
 * saas:tenant-lifecycle
 *
 * 扫描所有租户，根据 expires_at 和 saas.grace_days 配置计算 lifecycle 状态
 * 并触发相应副作用：
 *   - 进入 grace 期的租户（过期但在 grace_days 内）：记录到 log
 *   - 进入 frozen 期的租户（过期超过 grace_days）：记录到 log
 *
 * 同时为所有 active 租户写 tenant_usage_daily 快照。
 *
 * 本阶段 M3A 只做"扫描 + 记日志 + 快照写入"。
 * M3C 会增加通知、邮件等副作用。
 *
 * 推荐部署方式：cron 每小时跑一次
 *   0 * * * * cd /path/to/server && php think saas:tenant-lifecycle
 */
class SaasTenantLifecycle extends Command
{
    protected function configure()
    {
        $this->setName('saas:tenant-lifecycle')
             ->setDescription('扫描租户 lifecycle 状态，触发 grace/frozen 副作用并写日用量快照');
    }

    protected function execute(Input $input, Output $output)
    {
        $graceDays = (int) Config::get('saas.grace_days', 7);
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $graceStartStr = date('Y-m-d H:i:s', $now - $graceDays * 86400);

        $output->info("=== saas:tenant-lifecycle @ {$nowStr} ===");
        $output->info("grace_days: {$graceDays}");

        // 进入 grace 期：expires_at 在 grace 区间内 (grace_start < expires_at <= now)
        $graceTenants = Db::table('tenants')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$graceStartStr, $nowStr])
            ->select()
            ->toArray();

        // 进入 frozen 期：expires_at 早于 grace_start
        $frozenTenants = Db::table('tenants')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $graceStartStr)
            ->select()
            ->toArray();

        $output->info("Grace period tenants: " . count($graceTenants));
        foreach ($graceTenants as $t) {
            $output->writeln("  - [grace] tenant {$t['id']} ({$t['tenant_code']}) expired at {$t['expires_at']}");
        }

        $output->info("Frozen tenants: " . count($frozenTenants));
        foreach ($frozenTenants as $t) {
            $output->writeln("  - [frozen] tenant {$t['id']} ({$t['tenant_code']}) expired at {$t['expires_at']}");
        }

        // 为所有 active tenants 写 daily usage 快照
        $today = date('Y-m-d');
        $allTenants = Db::table('tenants')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->select()
            ->toArray();

        $output->info("Writing daily usage snapshot for " . count($allTenants) . " active tenants");
        foreach ($allTenants as $t) {
            $existing = Db::table('tenant_usage_daily')
                ->where('tenant_id', $t['id'])
                ->where('date', $today)
                ->find();

            $snapshotData = [
                'storage_used_bytes' => (int) ($t['storage_used_bytes'] ?? 0),
                // admin_count / user_count / request_count 保持 0（M3C 会填真实值）
            ];

            if ($existing) {
                Db::table('tenant_usage_daily')
                    ->where('tenant_id', $t['id'])
                    ->where('date', $today)
                    ->update($snapshotData);
            } else {
                Db::table('tenant_usage_daily')->insert(array_merge([
                    'tenant_id'     => (int) $t['id'],
                    'date'          => $today,
                    'admin_count'   => 0,
                    'user_count'    => 0,
                    'request_count' => 0,
                    'created_at'    => $nowStr,
                ], $snapshotData));
            }
        }

        $output->info("Done.");
        return 0;
    }
}
