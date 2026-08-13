<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin\steps;

use core\plugin\PluginPackageInstaller;
use core\saga\CompensatableStep;

/**
 * v2.7.0：解压插件 zip 到 plugins/<code>/。
 *
 * - BUILTIN 来源（磁盘已有源码）：execute 仅做存在性检查，compensate 不动盘
 * - ZIP 来源：execute 解压；compensate 仅当本次创建过 dir 时才 rrmdir，避免误删
 */
class ExtractZipStep implements CompensatableStep
{
    private bool $createdByThisRun = false;

    public function __construct(
        private string $pluginDir,
        private bool $isBuiltin,
        private string $packagePath,
        private PluginPackageInstaller $installer,
    ) {
    }

    public function name(): string
    {
        return 'ExtractZip';
    }

    public function execute(): void
    {
        if ($this->isBuiltin) {
            return; // 磁盘里已经是源
        }
        $this->installer->extract($this->packagePath, $this->pluginDir);
        $this->createdByThisRun = true;
    }

    public function compensate(): void
    {
        if (!$this->createdByThisRun) {
            return;
        }
        if (is_dir($this->pluginDir)) {
            $this->rrmdir($this->pluginDir);
        }
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
