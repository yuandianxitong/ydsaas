<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\service\saas;

use app\model\saas\TenantMobileBuild;
use app\repository\saas\TenantMobileBuildRepository;
use app\repository\saas\TenantRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\mobile\MobileBuilder;
use Throwable;

/**
 * 租户移动端构建编排：enqueue（写 queued 行）+ run（流转 running→success/failed）。
 *
 * 与 PluginBuildService 平级：分管移动端产物，避免插件云编译的状态混入。
 *
 * 同步执行模式：当前没有走 ThinkPHP queue（与 PluginBuildService 不同），
 * 因为移动端构建并发量小、状态机简单。后续如需要 queue，把 run() 调用
 * 移到 PluginBuildJob 之类的 Job 类即可。
 */
final class TenantMobileBuildService extends Service
{
    protected TenantMobileBuildRepository $buildRepo;
    protected TenantRepository $tenantRepository;
    protected MobileBuilder $builder;

    /**
     * 读取租户当前套餐 ID（构建行快照用）。
     */
    public function planIdForTenant(int $tenantId): int
    {
        return $this->tenantRepository->planIdOf($tenantId);
    }

    /**
     * 入队（写 queued 行），返回 build_id。Controller 拿到后可立即响应，
     * 实际构建由 worker / cron / job 调用 run()。
     */
    public function enqueue(int $tenantId, string $platform, int $planId, ?int $operatorId = null): int
    {
        if (!in_array($platform, TenantMobileBuild::PLATFORMS, true)) {
            throw new BusinessException("invalid platform: {$platform}", 422);
        }
        $now = date('Y-m-d H:i:s');

        // build_no 用 (tenant_id, build_no) unique 索引去重。
        // nextBuildNo 是 SELECT COUNT 估算，并发下可能拿到同一个值；
        // 遇到 duplicate key 重试最多 5 次（每次重新 count）。
        $buildId = 0;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $buildId = $this->buildRepo->insertRow([
                    'tenant_id'   => $tenantId,
                    'plan_id'     => $planId,
                    'platform'    => $platform,
                    'build_no'    => $this->buildRepo->nextBuildNo($tenantId),
                    'status'      => TenantMobileBuild::STATUS_QUEUED,
                    'operator_id' => $operatorId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
                break;
            } catch (\think\db\exception\PDOException $e) {
                // SQLSTATE 23000 = integrity constraint violation；MySQL 1062 = duplicate entry
                if (!str_contains($e->getMessage(), '1062')) {
                    throw $e;
                }
                if ($attempt >= 5) {
                    throw new BusinessException('build_no 生成失败，并发过高，请稍后重试', 503);
                }
                // 让出 CPU 后重试，分散冲突时间点
                usleep(20_000 + random_int(0, 80_000));
            }
        }

        // v2.6.0：把任务 dispatch 到 mobile-builds 队列，由 supervisord 的
        // 专用 worker 异步执行（独立 900s 超时）。
        // 测试 / CLI 兜底场景：QueueManager::push 可在 config/queue.php 把
        // default driver 切到 sync 来同步触发；或调用方直接 run() 仍可用。
        $pushed = \core\queue\QueueManager::push(
            \app\job\MobileBuildJob::class,
            ['build_id' => $buildId],
            'mobile-builds',
        );
        // push 失败（消息队列不可用 / 配置错误）时不能把行静默留在 queued —— 否则
        // 它会永远「排队」且没有 worker 来跑。直接标记 failed 并抛错给上层。
        if (!$pushed) {
            $this->buildRepo->markFailed($buildId, '入队失败：消息队列不可用（QueueManager::push 返回 false），请检查 Redis 与 mobile-builds worker');
            throw new BusinessException('构建入队失败：消息队列不可用，请稍后重试或联系管理员', 503);
        }

        return $buildId;
    }

    /**
     * 实际执行构建。可被 Job / cron / CLI 调用，多次调用幂等。
     */
    public function run(int $buildId): void
    {
        $build = $this->buildRepo->find($buildId);
        if (!$build) {
            return;
        }
        if ((int) $build['status'] !== TenantMobileBuild::STATUS_QUEUED) {
            return; // 已被其他 worker 取走或已完成
        }

        if (!$this->buildRepo->markRunning($buildId)) {
            return; // 已被取消或其它 worker 取走
        }

        try {
            $outcome = $this->builder->build(
                (int) $build['tenant_id'],
                $buildId,
                (string) $build['platform'],
            );

            // CAS：仅 status=running 可收尾，避免用户取消后被晚到的 worker 覆盖
            $runningOnly = [TenantMobileBuild::STATUS_RUNNING];

            if ($outcome->success) {
                $this->buildRepo->markSuccess($buildId, [
                    'artifact_path'         => $outcome->artifactPath,
                    'error_log'             => mb_substr($outcome->log, 0, 51200),
                    'included_plugins_json' => $outcome->includedPlugins,
                    'pages_json'            => $outcome->pagesJson,
                    'manifest_json'         => $outcome->manifestJson,
                    'driver'                => $outcome->driver,
                    'remote_job_id'         => $outcome->remoteJobId,
                    'artifact_url'          => $outcome->artifactUrl,
                    'runtime_json'          => $outcome->runtime !== [] ? $outcome->runtime : null,
                ]);
            } else {
                $this->buildRepo->markFailed($buildId, $outcome->log, [
                    'driver'        => $outcome->driver,
                    'remote_job_id' => $outcome->remoteJobId,
                    'runtime_json'  => $outcome->runtime !== [] ? $outcome->runtime : null,
                ], $runningOnly);
            }
        } catch (Throwable $e) {
            $this->buildRepo->markFailed(
                $buildId,
                \core\mobile\ErrorLogSanitizer::fromException($e, ['build_id' => $buildId]),
                [],
                [TenantMobileBuild::STATUS_RUNNING],
            );
        }
    }

    /**
     * 租户取消排队中或构建中的任务。远端 pnpm 不强杀，靠 remote BUILD_TIMEOUT 清理。
     *
     * @return array<string, mixed>
     */
    public function cancelForTenant(int $tenantId, int $buildId): array
    {
        $build = $this->buildRepo->findByTenantAndId($tenantId, $buildId);
        if (!$build) {
            throw new BusinessException('build not found', 404);
        }
        $status = (int) $build['status'];
        if (!in_array($status, [TenantMobileBuild::STATUS_QUEUED, TenantMobileBuild::STATUS_RUNNING], true)) {
            throw new BusinessException('仅排队中或构建中的任务可以取消', 422);
        }

        $ok = $this->buildRepo->markFailed(
            $buildId,
            '用户取消构建',
            [],
            [TenantMobileBuild::STATUS_QUEUED, TenantMobileBuild::STATUS_RUNNING],
        );
        if (!$ok) {
            throw new BusinessException('取消失败：任务状态已变更', 409);
        }

        return $this->buildRepo->findByTenantAndId($tenantId, $buildId) ?? $build;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listForTenant(int $tenantId, int $page = 1, int $limit = 20, ?string $platform = null): array
    {
        return $this->buildRepo->paginate($tenantId, $page, $limit, $platform);
    }

    public function findForTenant(int $tenantId, int $buildId): ?array
    {
        return $this->buildRepo->findByTenantAndId($tenantId, $buildId);
    }

    public function requeueForTenant(int $tenantId, int $buildId): array
    {
        $build = $this->buildRepo->findByTenantAndId($tenantId, $buildId);
        if (!$build) {
            throw new BusinessException('build not found', 404);
        }
        if ((int) $build['status'] !== TenantMobileBuild::STATUS_QUEUED) {
            throw new BusinessException('仅排队中的构建可以重投递', 422);
        }

        $pushed = \core\queue\QueueManager::push(
            \app\job\MobileBuildJob::class,
            ['build_id' => $buildId],
            'mobile-builds',
        );
        if (!$pushed) {
            $this->buildRepo->markFailed($buildId, '重投递失败：消息队列不可用，请检查 Redis 与 mobile-builds worker');
            throw new BusinessException('重投递失败：消息队列不可用', 503);
        }

        $this->buildRepo->touchQueued($buildId);
        return $this->buildRepo->findByTenantAndId($tenantId, $buildId) ?? $build;
    }

    /**
     * 把 running 超过阈值的任务强制收尾为 failed。
     * 由 cron 或平台监控接口调用。
     *
     * @return int 被收尾的条数
     */
    public function forceFailStuckRunning(int $thresholdSeconds = 1800): int
    {
        $stuck = $this->buildRepo->listStuckRunning($thresholdSeconds);
        foreach ($stuck as $row) {
            $this->buildRepo->markFailed(
                (int) $row['id'],
                "[force-fail] running > {$thresholdSeconds}s, forcibly closed",
            );
        }
        return count($stuck);
    }
}
