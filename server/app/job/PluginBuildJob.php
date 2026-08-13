<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\job;

use app\service\plugin\PluginBuildService;
use think\facade\App;
use think\facade\Log;
use think\queue\Job;

/**
 * 异步执行 PluginBuildService.run(build_id)。
 *
 * data 形如 ['build_id' => N]
 */
class PluginBuildJob
{
    public function fire(Job $job, array $data): void
    {
        $buildId = (int) ($data['build_id'] ?? 0);
        if ($buildId <= 0) {
            $job->delete();
            return;
        }
        try {
            /** @var PluginBuildService $service */
            $service = App::getInstance()->make(PluginBuildService::class);
            $service->run($buildId);
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('PluginBuildJob 执行失败', ['build_id' => $buildId, 'error' => $e->getMessage()]);
            $job->delete();
        }
    }
}
