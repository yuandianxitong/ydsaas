<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\ShellExecutor;

/**
 * 主前端云编译执行器。
 *
 * 流程：
 *   1. cd 进 sourceDir（platform/ 或 tenant/）
 *   2. 跑 pnpm install --frozen-lockfile
 *   3. 跑 sync-plugins.mjs --target <target>（把插件源码软链接到前端）
 *   4. 跑 pnpm build → vite 输出到 publicDir/<target>.build-tmp/（需要 vite.config.ts 配合）
 *   5. 无条件跑 sync-plugins.mjs --target <target> --clean（清理软链接，即使 build 失败）
 *   6. 校验产物（index.html 存在）
 *   7. 原子切换：
 *      mv publicDir/<target>          publicDir/<target>.bak-<ts>  (如旧产物存在)
 *      mv publicDir/<target>.build-tmp publicDir/<target>
 *      rm -rf publicDir/<target>.bak-<ts>
 *
 * 失败时不动旧产物。
 *
 * 注意：Stage 5 实现假设 vite.config.ts 的 outDir 已被 caller 通过环境变量
 * 切换到临时目录（如 BUILD_TMP=1 时输出到 <target>.build-tmp/）。具体接入留
 * 给前端工程后续小改动。本 Stage 测试用 mock 模拟该行为。
 */
class PluginBuilder
{
    public function __construct(
        private readonly ShellExecutor $shell,
    ) {
    }

    /**
     * @return array{exitCode: int, log: string, artifactPath: string}
     */
    public function build(string $target, string $sourceDir, string $publicDir): array
    {
        $logBuffer = '';
        $tmpDir    = rtrim($publicDir, '/') . '/' . $target . '.build-tmp';
        $finalDir  = rtrim($publicDir, '/') . '/' . $target;
        $repoRoot  = dirname($sourceDir);
        $syncCmd   = sprintf('node %s/scripts/sync-plugins.mjs --target %s', $repoRoot, $target);
        $cleanCmd  = $syncCmd . ' --clean';

        // 1. pnpm install
        $r = $this->shell->exec('pnpm install --frozen-lockfile', $sourceDir);
        $logBuffer .= "\n== pnpm install ==\n" . $r['stdout'] . "\n" . $r['stderr'];
        if ($r['exitCode'] !== 0) {
            return ['exitCode' => $r['exitCode'], 'log' => $logBuffer, 'artifactPath' => ''];
        }

        // 2. sync-plugins: symlink plugin source into frontend workspace
        $r = $this->shell->exec($syncCmd, $repoRoot);
        $logBuffer .= "\n== sync-plugins ==\n" . $r['stdout'] . "\n" . $r['stderr'];
        if ($r['exitCode'] !== 0) {
            return ['exitCode' => $r['exitCode'], 'log' => $logBuffer, 'artifactPath' => ''];
        }

        // 3. pnpm build
        $buildResult = $this->shell->exec('pnpm build', $sourceDir);
        $logBuffer .= "\n== pnpm build ==\n" . $buildResult['stdout'] . "\n" . $buildResult['stderr'];

        // 4. sync-plugins --clean (unconditional — run even if build failed)
        $r = $this->shell->exec($cleanCmd, $repoRoot);
        $logBuffer .= "\n== sync-plugins --clean ==\n" . $r['stdout'] . "\n" . $r['stderr'];

        if ($buildResult['exitCode'] !== 0) {
            if (is_dir($tmpDir)) {
                $this->rrmdir($tmpDir);
            }
            return ['exitCode' => $buildResult['exitCode'], 'log' => $logBuffer, 'artifactPath' => ''];
        }

        // 5. 校验产物
        if (!is_file($tmpDir . '/index.html')) {
            $logBuffer .= "\n[builder] missing artifact: {$tmpDir}/index.html";
            if (is_dir($tmpDir)) {
                $this->rrmdir($tmpDir);
            }
            return ['exitCode' => 1, 'log' => $logBuffer, 'artifactPath' => ''];
        }

        // 6. 原子切换
        $backupDir = '';
        if (is_dir($finalDir)) {
            $backupDir = $finalDir . '.bak-' . time();
            if (!rename($finalDir, $backupDir)) {
                $logBuffer .= "\n[builder] failed to rename old artifact to backup";
                return ['exitCode' => 1, 'log' => $logBuffer, 'artifactPath' => ''];
            }
        }
        if (!rename($tmpDir, $finalDir)) {
            if ($backupDir !== '' && is_dir($backupDir)) {
                rename($backupDir, $finalDir);
            }
            $logBuffer .= "\n[builder] failed to promote new artifact";
            return ['exitCode' => 1, 'log' => $logBuffer, 'artifactPath' => ''];
        }
        if ($backupDir !== '' && is_dir($backupDir)) {
            $this->rrmdir($backupDir);
        }

        return ['exitCode' => 0, 'log' => $logBuffer, 'artifactPath' => $finalDir];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
