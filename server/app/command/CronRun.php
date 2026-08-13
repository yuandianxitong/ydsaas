<?php

/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use app\service\system\CronJobService;
use Cron\CronExpression;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * cron:run
 *
 * 定时任务调度 tick：扫描启用的 cron_jobs，按 cron 表达式判断"本分钟是否到点"，
 * 到点的走 CronJobService::runCronJob() 执行（复用白名单校验 + 进程内 Console::call + 日志/统计）。
 *
 * 部署：系统 crontab 每分钟调用一次即可驱动整张表所有任务（一行搞定）：
 *   * * * * * cd /path/to/server && php think cron:run >> /var/log/cron-run.log 2>&1
 */
class CronRun extends Command
{
    protected function configure(): void
    {
        $this->setName('cron:run')
            ->setDescription('定时任务调度 tick：执行 cron_jobs 中到点的启用任务（供系统 crontab 每分钟调用）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $now  = new \DateTime();
        $jobs = Db::table('cron_jobs')->where('status', 1)->whereNull('deleted_at')->select()->all();

        $service = new CronJobService();
        $due = 0;
        $ran = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            if (!$this->isDue($job, $now)) {
                continue;
            }
            $due++;
            try {
                $r = $service->runCronJob((int) $job['id']);
                if (($r['status'] ?? 0) === 1) {
                    $ran++;
                } else {
                    $failed++;
                    $output->writeln(sprintf('  [%s] 失败: %s', $job['command'], $r['error'] ?? ''));
                }
            } catch (\Throwable $e) {
                $failed++;
                $output->writeln(sprintf('  [%s] 异常: %s', $job['command'] ?? '?', $e->getMessage()));
            }
        }

        $output->writeln(sprintf('cron:run @ %s — due=%d, ran=%d, failed=%d', $now->format('Y-m-d H:i'), $due, $ran, $failed));

        return $failed > 0 ? 1 : 0;
    }

    /**
     * 该任务本分钟是否应执行：表达式到点 + 本分钟未跑过（防同分钟重复触发）。
     * 公开以便测试（用固定 $now 注入，不依赖真实时间）。
     */
    public function isDue(array $job, \DateTimeInterface $now): bool
    {
        $expr = trim((string) ($job['expression'] ?? ''));
        if ($expr === '') {
            return false;
        }
        try {
            $cron = new CronExpression($expr);
        } catch (\Throwable) {
            return false; // 非法表达式：跳过而非崩溃
        }
        if (!$cron->isDue($now)) {
            return false;
        }
        // 同分钟去重：last_run_at 已落在当前分钟则不重复执行
        $last = $job['last_run_at'] ?? null;
        if ($last !== null && substr((string) $last, 0, 16) === $now->format('Y-m-d H:i')) {
            return false;
        }
        return true;
    }
}
