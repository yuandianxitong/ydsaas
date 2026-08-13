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
use think\facade\App;

/**
 * H5 构建产物发布：把 success 状态的 artifact 拷贝到
 * server/public/mobile-tenants/{tenant_code}/，由 nginx 静态服务。
 *
 * release 后：
 *   - 状态 success → released
 *   - 同租户上一次的 released 产物保留磁盘但状态被 markStatus 回 success（手工切回时可用）
 */
final class H5ReleaseService extends Service
{
    protected TenantMobileBuildRepository $buildRepo;
    protected TenantRepository $tenantRepository;

    public function release(int $tenantId, int $buildId): array
    {
        $build = $this->buildRepo->findByTenantAndId($tenantId, $buildId);
        if (!$build) {
            throw new BusinessException('build not found', 404);
        }
        if ((string) $build['platform'] !== 'h5') {
            throw new BusinessException('only h5 builds can be released this way', 422);
        }
        $status = (int) $build['status'];
        if ($status !== TenantMobileBuild::STATUS_SUCCESS && $status !== TenantMobileBuild::STATUS_RELEASED) {
            throw new BusinessException("build status must be success/released, got {$status}", 422);
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            throw new BusinessException('tenant not found', 404);
        }
        $tenantCode = (string) ($tenant['tenant_code'] ?? '');
        if ($tenantCode === '') {
            throw new BusinessException('tenant has no code', 422);
        }

        $artifact = (string) $build['artifact_path'];
        if ($artifact === '' || !is_dir($artifact)) {
            throw new BusinessException("artifact dir not found: {$artifact}", 422);
        }

        $publicDir = rtrim(App::getRootPath(), '/') . '/public/mobile-tenants/' . $tenantCode;
        // 原子替换：先复制到 .tmp-{rand}，再 rename 到目标目录，最后异步删旧版。
        // 这样 nginx 不会有「目录已删尚未拷完」的可用性窗口。
        $tmpDir  = $publicDir . '.tmp-' . bin2hex(random_bytes(4));
        $oldDir  = $publicDir . '.old-' . bin2hex(random_bytes(4));

        $this->ensureParent($publicDir);
        if (is_dir($tmpDir)) {
            $this->rrmdir($tmpDir);
        }
        if (!mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new BusinessException("failed to mkdir {$tmpDir}", 500);
        }
        $this->copyDir($artifact, $tmpDir);

        // 旧目录改名出去（rename 是 inode 级原子，nginx 仍可服务旧目录）
        $hasOld = is_dir($publicDir);
        if ($hasOld && !rename($publicDir, $oldDir)) {
            $this->rrmdir($tmpDir);
            throw new BusinessException("failed to swap old release at {$publicDir}", 500);
        }
        if (!rename($tmpDir, $publicDir)) {
            // 滚回旧版
            if ($hasOld) {
                @rename($oldDir, $publicDir);
            }
            $this->rrmdir($tmpDir);
            throw new BusinessException("failed to atomic-rename {$tmpDir} → {$publicDir}", 500);
        }
        // 旧版后台删
        if ($hasOld) {
            $this->rrmdir($oldDir);
        }

        // 把当前 build 标记为 released
        $this->buildRepo->markStatus($buildId, TenantMobileBuild::STATUS_RELEASED);

        return $this->buildRepo->findByTenantAndId($tenantId, $buildId) ?? [];
    }

    private function ensureParent(string $dir): void
    {
        $parent = dirname($dir);
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new BusinessException("failed to mkdir parent {$parent}", 500);
        }
    }

    private function copyDir(string $src, string $dst): void
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iter as $item) {
            $relative = substr($item->getPathname(), strlen($src) + 1);
            $dstPath  = $dst . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($dstPath) && !mkdir($dstPath, 0775, true) && !is_dir($dstPath)) {
                    throw new BusinessException("failed to mkdir {$dstPath}", 500);
                }
            } else {
                copy($item->getPathname(), $dstPath);
            }
        }
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
