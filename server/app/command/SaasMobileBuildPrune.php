<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\command;

use app\repository\saas\TenantMobileBuildRepository;
use core\mobile\MobileBuildEnv;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\App;
use think\facade\Db;

/**
 * saas:mobile-build-prune
 *
 * 每租户每平台仅保留最近 N 个产物（默认 5），其余删磁盘 + DB 行；
 * 同时截断超过 90 天的 error_log 到 50KB 以内。
 *
 *   --keep=5           每租户每平台保留多少个 (默认 .env MOBILE_BUILD_KEEP_N)
 *   --tenant=ID        只处理某租户
 *   --dry-run          只打印不落库 / 不删盘
 */
class SaasMobileBuildPrune extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:mobile-build-prune')
            ->setDescription('Prune old mobile build artifacts and truncate stale error_log')
            ->addOption('keep', null, Option::VALUE_OPTIONAL, '每租户每平台保留多少个', null)
            ->addOption('tenant', null, Option::VALUE_OPTIONAL, '只处理某 tenant_id', null)
            ->addOption('dry-run', null, Option::VALUE_NONE, '只打印不落库');
    }

    protected function execute(Input $input, Output $output): int
    {
        $keep = (int) ($input->getOption('keep') ?? MobileBuildEnv::getInt('MOBILE_BUILD_KEEP_N', 5));
        if ($keep < 1) {
            $output->writeln('keep must be >= 1');
            return 1;
        }
        $onlyTenant = $input->getOption('tenant');
        $onlyTenant = $onlyTenant !== null ? (int) $onlyTenant : null;
        $dryRun = (bool) $input->getOption('dry-run');

        $repo = App::make(TenantMobileBuildRepository::class);

        // 收集 (tenant_id, platform) 唯一组合
        $combosQ = Db::table('tenant_mobile_builds');
        if ($onlyTenant !== null) {
            $combosQ = $combosQ->where('tenant_id', $onlyTenant);
        }
        $combos = $combosQ->distinct(true)
            ->field('tenant_id, platform')
            ->select()
            ->toArray();

        $deletedRows = 0;
        $deletedDirs = 0;

        foreach ($combos as $combo) {
            $tid = (int) $combo['tenant_id'];
            $plat = (string) $combo['platform'];
            $stale = $repo->pruneCandidates($tid, $plat, $keep);
            foreach ($stale as $row) {
                $output->writeln(
                    sprintf('prune tenant=%d platform=%s build=%s artifact=%s', $tid, $plat, $row['build_no'], $row['artifact_path']),
                );
                if (!$dryRun) {
                    if (!empty($row['artifact_path']) && is_dir((string) $row['artifact_path'])) {
                        $this->rrmdir((string) $row['artifact_path']);
                        $deletedDirs++;
                    }
                    Db::table('tenant_mobile_builds')->where('id', (int) $row['id'])->delete();
                    $deletedRows++;
                }
            }
        }

        // 截断历史 error_log
        $stale = Db::table('tenant_mobile_builds')
            ->where('finished_at', '<', date('Y-m-d H:i:s', strtotime('-90 days')))
            ->where(function ($q) {
                $q->whereRaw('CHAR_LENGTH(error_log) > 51200');
            })
            ->column('id');
        foreach ($stale as $id) {
            $log = (string) Db::table('tenant_mobile_builds')->where('id', $id)->value('error_log');
            $truncated = mb_substr($log, 0, 51200) . "\n... [truncated by prune cron]";
            if (!$dryRun) {
                Db::table('tenant_mobile_builds')->where('id', $id)->update(['error_log' => $truncated]);
            }
            $output->writeln("truncated error_log of build #{$id}");
        }

        $output->writeln(sprintf(
            'done: %d rows pruned, %d artifact dirs removed, %d logs truncated %s',
            $deletedRows,
            $deletedDirs,
            count($stale),
            $dryRun ? '[dry-run]' : '',
        ));
        return 0;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_link($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
