<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\plugin;

use core\exception\BusinessException;
use core\plugin\PluginManifestValidator;
use core\plugin\PluginPackageInstaller;
use core\runtime\RuntimePaths;
use think\facade\App;
use ZipArchive;

/**
 * 把本地 plugins/<code>/ 打包成 runtime/plugin-packages/<code>-<version>.zip。
 * 复用 PluginPackageInstaller::inspect 作为校验闸：产出的 zip 必能通过上传/安装。
 */
class PluginPacker
{
    /** zip 根允许的顶层文件 */
    private const ALLOWED_FILES = ['plugin.json', 'README.md', 'LICENSE', 'CHANGELOG.md', 'icon.png'];

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? App::getRootPath(), '/\\');
    }

    /**
     * @return string 最终 zip 的绝对路径
     */
    public function pack(string $code, ?string $outputDir, bool $force): string
    {
        $dir = $this->root . '/plugins/' . $code;
        $manifestPath = $dir . '/plugin.json';
        if (!is_dir($dir) || !is_file($manifestPath)) {
            throw new BusinessException("插件不存在或缺少 plugin.json：{$dir}", 404);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            throw new BusinessException('plugin.json 不是合法 JSON', 422);
        }
        $errors = (new PluginManifestValidator())->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('plugin.json 校验失败：' . implode('; ', $errors), 422);
        }
        if (($manifest['code'] ?? null) !== $code) {
            throw new BusinessException("目录名 [{$code}] 与 plugin.json 的 code [{$manifest['code']}] 不一致", 422);
        }

        // 1. 构建临时 zip（plugin.json 在根）
        $tmpZip = sys_get_temp_dir() . '/plugin-pack-' . $code . '-' . uniqid() . '.zip';
        $iconRaw = isset($manifest['icon']) && is_string($manifest['icon']) ? $manifest['icon'] : null;
        // HTTP URL icons are remote — no local file to bundle; only include local filename icons
        $iconFile = ($iconRaw !== null && !preg_match('#^https?://#i', $iconRaw)) ? $iconRaw : null;
        $this->buildZip($dir, $tmpZip, $iconFile);

        // 2. 过运行时检查器（结构 + 扩展名 + 危险代码 + manifest 重解析）
        try {
            (new PluginPackageInstaller())->inspect($tmpZip);
        } catch (\Throwable $e) {
            @unlink($tmpZip);
            throw new BusinessException('打包校验失败（' . $e->getMessage() . '）', 422);
        }

        // 3. 落地到 runtime/plugin-packages/<code>-<version>.zip
        $outDir = $outputDir !== null && $outputDir !== ''
            ? rtrim($outputDir, '/\\')
            : RuntimePaths::pluginPackagesDir($this->root);
        if (!is_dir($outDir)) {
            mkdir($outDir, RuntimePaths::DIR_MODE, true);
        }
        $finalPath = $outDir . '/' . $code . '-' . $manifest['version'] . '.zip';
        if (is_file($finalPath) && !$force) {
            @unlink($tmpZip);
            throw new BusinessException("目标已存在：{$finalPath}（用 --force 覆盖）", 409);
        }
        if (!rename($tmpZip, $finalPath)) {
            if (!copy($tmpZip, $finalPath)) {
                @unlink($tmpZip);
                throw new BusinessException("无法移动打包文件到目标位置：{$finalPath}", 500);
            }
            @unlink($tmpZip);
        }
        return $finalPath;
    }

    private function buildZip(string $dir, string $zipPath, ?string $iconFile = null): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BusinessException('无法创建临时 zip', 500);
        }
        // Merge manifest icon into the top-level file list (handles any image filename, e.g. logo.svg)
        $topFiles = self::ALLOWED_FILES;
        if ($iconFile !== null && !in_array($iconFile, $topFiles, true)) {
            $topFiles[] = $iconFile;
        }
        try {
            foreach ($topFiles as $f) {
                $p = $dir . '/' . $f;
                if (is_file($p)) {
                    $zip->addFile($p, $f);
                }
            }
            foreach (PluginPackageInstaller::ALLOWED_TOP_DIRS as $d) {
                $sub = $dir . '/' . $d;
                if (is_dir($sub)) {
                    $this->addDir($zip, $sub, $d);
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($zipPath);
            throw $e;
        }
        $zip->close();
    }

    private function addDir(ZipArchive $zip, string $absDir, string $zipPrefix): void
    {
        $skip = ['.DS_Store', '.git', 'node_modules', 'dist'];
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            if ($item->isLink()) {
                continue; // 不跟随符号链接，避免打包到插件目录之外的文件
            }
            $name = $item->getFilename();
            if (in_array($name, $skip, true) || str_ends_with($name, '.log')) {
                continue;
            }
            $rel = $zipPrefix . '/' . substr($item->getPathname(), strlen($absDir) + 1);
            $rel = str_replace('\\', '/', $rel);
            if ($item->isDir()) {
                $zip->addEmptyDir($rel);
            } elseif ($item->isFile()) {
                $zip->addFile($item->getPathname(), $rel);
            }
        }
    }
}
