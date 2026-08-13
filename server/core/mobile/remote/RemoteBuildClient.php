<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\remote;

/**
 * 远程构建服务 HTTP 客户端契约。RemoteMobileBuildDriver 依赖此接口，
 * 测试注入 fake 实现，避免真实 HTTP。
 */
interface RemoteBuildClient
{
    /**
     * 上传 workspace.zip 创建构建任务。
     * @return array{job_id: string, status: string}
     */
    public function createJob(string $base, string $token, string $workspaceZip, string $platform, int $tenantId, int $buildId, int $timeoutSec): array;

    /**
     * 查询任务状态。
     * @return array{status: string, artifact_url: string, log_excerpt: string}
     */
    public function getJob(string $base, string $token, string $jobId, int $timeoutSec): array;

    /**
     * 下载 artifact.zip 到本地 destZipPath。失败抛 \RuntimeException。
     */
    public function downloadArtifact(string $base, string $token, string $jobId, string $destZipPath, int $timeoutSec): void;
}
