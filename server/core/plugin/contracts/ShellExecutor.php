<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\plugin\contracts;

/**
 * 抽象 shell 执行器。
 *
 * PluginBuilder 通过此接口跑 pnpm install / pnpm build，便于测试 mock。
 * 生产用 ProcShellExecutor（proc_open），测试用 MockShellExecutor（预录返回值）。
 */
interface ShellExecutor
{
    /**
     * 运行 shell 命令并捕获结果。
     *
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function exec(string $cmd, string $cwd, int $timeoutSeconds = 600): array;
}
