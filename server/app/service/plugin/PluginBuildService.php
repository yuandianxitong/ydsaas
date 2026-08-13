<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\job\PluginBuildJob;
use app\model\plugin\PluginBuild;
use app\repository\plugin\PluginBuildRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\PluginBuilder;
use core\queue\QueueManager;
use think\facade\App;

/**
 * 云编译编排：enqueue（写行 + 投 Job）+ run（实际跑构建，由 Job 调用）。
 */
class PluginBuildService extends Service
{
    protected PluginBuildRepository $buildRepo;
    protected PluginBuilder $builder;

    /**
     * 入队一个构建任务，立即返回 build_id。
     *
     * @param string $target   'platform' | 'tenant'
     * @param string $trigger  'install' | 'upgrade' | 'enable' | 'disable' | 'uninstall' | 'manual'
     */
    public function enqueue(string $target, string $trigger, ?int $pluginId = null, ?int $operatorId = null): int
    {
        if (!in_array($target, [PluginBuild::TARGET_PLATFORM, PluginBuild::TARGET_TENANT], true)) {
            throw new BusinessException("invalid target: {$target}", 422);
        }

        $now = date('Y-m-d H:i:s');
        $buildId = (int) $this->buildRepo->create([
            'target'      => $target,
            'trigger'     => $trigger,
            'plugin_id'   => $pluginId,
            'status'      => PluginBuild::STATUS_QUEUED,
            'log'         => '',
            'operator_id' => $operatorId,
            'created_at'  => $now,
        ])['id'];

        QueueManager::push(PluginBuildJob::class, ['build_id' => $buildId]);

        return $buildId;
    }

    /**
     * 实际执行构建。由 PluginBuildJob.fire() 调用，也支持 CLI 同步触发。
     */
    public function run(int $buildId): void
    {
        $build = $this->buildRepo->find($buildId);
        if (!$build) {
            return; // job 重试时记录可能已被清掉
        }
        if ((int) $build['status'] !== PluginBuild::STATUS_QUEUED) {
            return; // 已被其他 worker 取走或已完成
        }

        $this->buildRepo->markRunning($buildId);

        $target = (string) $build['target'];
        $rootPath = App::getRootPath();
        // rootPath 是 server/，源码在 ../platform 或 ../tenant
        $sourceDir = realpath($rootPath . '../' . $target) ?: '';
        $publicDir = $rootPath . 'public';

        if ($sourceDir === '' || !is_dir($sourceDir)) {
            $this->buildRepo->markFailed($buildId, "[service] source dir not found: {$rootPath}../{$target}");
            return;
        }

        try {
            $result = $this->builder->build($target, $sourceDir, $publicDir);
            if ($result['exitCode'] === 0) {
                $this->buildRepo->markSuccess($buildId, $result['log'], $result['artifactPath']);
            } else {
                $this->buildRepo->markFailed($buildId, $result['log']);
            }
        } catch (\Throwable $e) {
            $this->buildRepo->markFailed($buildId, '[exception] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
