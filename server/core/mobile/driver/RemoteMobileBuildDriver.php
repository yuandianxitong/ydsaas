<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\driver;

use core\mobile\MobileBuildContext;
use core\mobile\MobileBuildDriverResult;
use core\mobile\MobileBuildEnv;
use core\mobile\remote\RemoteBuildClient;

/**
 * 远程构建：打包 workspace → POST 远端 → 轮询 → 下载 artifact.zip → 解压成本地目录。
 * 产物 artifactPath 为本地目录，契约与 local/docker 一致。
 *
 * 非 final——测试匿名子类覆盖 now()/pause() 驱动轮询循环、避免真 sleep。
 */
class RemoteMobileBuildDriver implements MobileBuildDriver
{
    private const DEFAULT_TIMEOUT_SEC = 1800;
    private const DEFAULT_POLL_SEC = 5;

    public function __construct(private readonly RemoteBuildClient $client)
    {
    }

    public function build(MobileBuildContext $ctx): MobileBuildDriverResult
    {
        $platformDir = $this->platformDirName($ctx->platform);
        if ($platformDir === null) {
            return $this->fail("[remote] unknown platform: {$ctx->platform}", null);
        }

        $base  = MobileBuildEnv::get('MOBILE_BUILD_REMOTE_URL', '') ?? '';
        $token = MobileBuildEnv::get('MOBILE_BUILD_REMOTE_TOKEN', '') ?? '';
        if ($base === '') {
            return $this->fail('[remote] MOBILE_BUILD_REMOTE_URL not configured', null);
        }
        if ($token === '') {
            return $this->fail('[remote] MOBILE_BUILD_REMOTE_TOKEN not configured', null);
        }

        $timeout = max(1, MobileBuildEnv::getInt('MOBILE_BUILD_REMOTE_TIMEOUT_SEC', self::DEFAULT_TIMEOUT_SEC));
        $poll    = max(1, MobileBuildEnv::getInt('MOBILE_BUILD_REMOTE_POLL_INTERVAL_SEC', self::DEFAULT_POLL_SEC));

        $jobId = null;
        try {
            $workspaceZip = $ctx->workspaceDir . '/workspace.zip';
            $this->zipDir($ctx->uniappDir, $workspaceZip, 'uniapp');

            $created = $this->client->createJob($base, $token, $workspaceZip, $ctx->platform, $ctx->tenantId, $ctx->buildId, $timeout);
            $jobId   = $created['job_id'];
            if ($jobId === '') {
                return $this->fail('[remote] createJob returned empty job_id', null);
            }

            $deadline = $this->now() + $timeout;
            $logExcerpt = '';
            $artifactUrl = '';
            while (true) {
                $job = $this->client->getJob($base, $token, $jobId, $timeout);
                $status = $job['status'];
                $logExcerpt = $job['log_excerpt'];
                $artifactUrl = $job['artifact_url'];

                if ($status === 'success') {
                    break;
                }
                if ($status === 'failed') {
                    return $this->fail("[remote] remote build failed\n" . $logExcerpt, $jobId);
                }
                if ($this->now() >= $deadline) {
                    return $this->fail('[remote] poll timeout waiting for remote build', $jobId);
                }
                $this->pause($poll);
            }

            // 下载 + 解压成本地目录
            $artifactZip = $ctx->workspaceDir . '/artifact.zip';
            $this->client->downloadArtifact($base, $token, $jobId, $artifactZip, $timeout);

            $artifactDir = $ctx->workspaceDir . '/artifact/' . $platformDir;
            $this->unzipTo($artifactZip, $artifactDir);
            if (!is_dir($artifactDir)) {
                return $this->fail("[remote] artifact dir not found after unzip: {$artifactDir}", $jobId);
            }

            return new MobileBuildDriverResult(
                success: true,
                artifactPath: $artifactDir,
                log: $logExcerpt,
                remoteJobId: $jobId,
                artifactUrl: $artifactUrl,
                runtime: ['driver' => 'remote', 'remote_job_id' => $jobId],
            );
        } catch (\Throwable $e) {
            return $this->fail('[remote] ' . $e->getMessage(), $jobId);
        }
    }

    private function platformDirName(string $platform): ?string
    {
        return match ($platform) {
            'h5'        => 'h5',
            'mp-weixin' => 'mp-weixin',
            'app'       => 'app',
            default     => null,
        };
    }

    /** 把目录打成 zip，内部根为 $rootName（如 uniapp/...）。 */
    private function zipDir(string $srcDir, string $destZip, string $rootName): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($destZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("[remote] cannot create workspace zip: {$destZip}");
        }
        $base = rtrim($srcDir, '/');
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        try {
            /** @var \SplFileInfo $file */
            foreach ($it as $file) {
                $local = $rootName . '/' . substr($file->getPathname(), strlen($base) + 1);
                if ($file->isDir()) {
                    $zip->addEmptyDir($local);
                } else {
                    $zip->addFile($file->getPathname(), $local);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** 解压 zip 到目标目录（先确保目录存在）。 */
    private function unzipTo(string $zipPath, string $destDir): void
    {
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new \RuntimeException("[remote] cannot create artifact dir: {$destDir}");
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("[remote] cannot open artifact zip: {$zipPath}");
        }
        try {
            $zip->extractTo($destDir);
        } finally {
            $zip->close();
        }
    }

    private function fail(string $log, ?string $jobId): MobileBuildDriverResult
    {
        $runtime = ['driver' => 'remote'];
        if ($jobId !== null && $jobId !== '') {
            $runtime['remote_job_id'] = $jobId;
        }
        return new MobileBuildDriverResult(
            success: false,
            artifactPath: '',
            log: $log,
            remoteJobId: ($jobId !== null && $jobId !== '') ? $jobId : null,
            runtime: $runtime,
        );
    }

    /** 测试缝：当前秒级时间戳。 */
    protected function now(): int
    {
        return time();
    }

    /** 测试缝：轮询间隔等待。 */
    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }
}
