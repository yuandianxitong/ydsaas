<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\job;

use app\service\saas\TenantMobileBuildService;
use think\facade\App;
use think\facade\Log;
use think\queue\Job;

/**
 * 异步执行 TenantMobileBuildService.run(build_id)。
 *
 * data 形如 ['build_id' => N]
 *
 * 与 PluginBuildJob 同模板。专走 `mobile-builds` 队列以便对应 worker 设
 * 单独的长超时（900s），与 plugin build 的 60s 短超时解耦。
 *
 * 失败策略：tries=1（supervisord 配置），失败的任务由 Service::run 内部
 * 抓异常并把 build 行标记 failed；Job 层只兜底 throw → 该任务从队列移除。
 */
class MobileBuildJob
{
    public function fire(Job $job, array $data): void
    {
        $buildId = (int) ($data['build_id'] ?? 0);
        if ($buildId <= 0) {
            $job->delete();
            return;
        }
        try {
            /** @var TenantMobileBuildService $service */
            $service = App::getInstance()->make(TenantMobileBuildService::class);
            $service->run($buildId);
            $job->delete();
        } catch (\Throwable $e) {
            // run() 内部已捕获普通错误并写 error_log。能漏到这里说明是基础设施级异常
            // （容器 / Redis / DB 等）。写日志后删任务，避免无限重试堵队列。
            Log::error('MobileBuildJob 执行失败', [
                'build_id' => $buildId,
                'error'    => $e->getMessage(),
            ]);
            $job->delete();
        }
    }
}
