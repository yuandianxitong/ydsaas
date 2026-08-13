<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 把 uniapp/ 模板复制到指定产物根目录。
 *
 * 跳过：node_modules / dist / .cache / .git / .DS_Store。
 * 不跳过 src/ 内的 plugin subpackage 符号链接（pnpm 兼容）——直接 follow + 拷副本。
 */
final class TemplateCopier
{
    private const SKIP_DIRS = ['node_modules', 'dist', '.cache', '.git'];
    private const SKIP_FILES = ['.DS_Store'];

    /**
     * 复制 $sourceDir 下全部内容到 $targetDir/uniapp/。
     * 若 $targetDir 已存在则先清空。
     *
     * @return string  实际拷贝结果根（{$targetDir}/uniapp）
     */
    public function copy(string $sourceDir, string $targetDir): string
    {
        $source = rtrim($sourceDir, '/');
        $target = rtrim($targetDir, '/') . '/uniapp';

        if (!is_dir($source)) {
            throw new \RuntimeException("template source not found: {$source}");
        }

        if (is_dir($target)) {
            $this->rrmdir($target);
        }
        if (!mkdir($target, 0775, true) && !is_dir($target)) {
            throw new \RuntimeException("failed to create {$target}");
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iter as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            if ($this->shouldSkip($relative)) {
                continue;
            }
            // 关键：src/modules 下的「符号链接条目」是开发态由 scripts/sync-plugin-uniapp.mjs
            // 注入的插件源码（指向 server/plugins/{code}/uniapp）。独立构建要从干净壳开始，
            // 跳过所有这类链接；插件源码后续由 PluginUniappCopier 按租户权益单独复制。
            // 注意：内置模块（login/user/webview/...）是 base 模板里的真实目录，不跳过。
            if ($this->isPluginSymlinkUnderModules($relative, $item)) {
                continue;
            }
            // 同理：src/components/diy/plugins 下的符号链接是开发态注入的插件 DIY 渲染器
            // （指向 server/plugins/{code}/uniapp/components/diy）。跟随复制会把全部插件的
            // 渲染器源码带进每个租户的构建目录；跳过后由 PluginUniappCopier::copyDiyRenderers
            // 按租户权益单独复制。
            if ($this->isPluginSymlinkUnderDiyPlugins($relative, $item)) {
                continue;
            }
            $dst = $target . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
                    throw new \RuntimeException("failed to mkdir {$dst}");
                }
            } else {
                // symlink: follow + copy as real file（避免产物里残留指向 server/plugins 的链接）
                $real = $item->isLink() ? readlink($item->getPathname()) : $item->getPathname();
                if (!is_string($real)) {
                    continue;
                }
                if ($item->isLink() && !str_starts_with($real, '/')) {
                    $real = realpath(dirname($item->getPathname()) . '/' . $real) ?: $real;
                }
                if (is_dir($real)) {
                    // 符号链接指向目录的情况：递归把目标目录拷过来
                    $this->copyDir($real, $dst);
                } else {
                    copy($real, $dst);
                }
            }
        }

        return $target;
    }

    /**
     * 判断 src/modules/{x} 是否是开发态的插件 symlink（指向 server/plugins/{code}/uniapp）。
     * 这类条目独立构建时不能跟随复制，否则未授权插件源码会落入构建目录。
     */
    private function isPluginSymlinkUnderModules(string $relative, \SplFileInfo $item): bool
    {
        if (!$item->isLink()) {
            return false;
        }
        $parts = explode('/', $relative);
        if (count($parts) < 2 || $parts[0] !== 'src' || $parts[1] !== 'modules') {
            return false;
        }
        // 进一步：第二级条目（src/modules/<x>）才是 sync 脚本注入的 symlink；
        // symlink 下再嵌套的真实文件不会触发（被父链接拦下）
        return count($parts) === 3;
    }

    /**
     * 判断 src/components/diy/plugins/{x} 是否是开发态的插件 DIY 渲染器 symlink
     * （由 scripts/sync-plugin-uniapp.mjs 注入，指向 server/plugins/{code}/uniapp/components/diy）。
     */
    private function isPluginSymlinkUnderDiyPlugins(string $relative, \SplFileInfo $item): bool
    {
        if (!$item->isLink()) {
            return false;
        }
        $parts = explode('/', $relative);
        return count($parts) === 5
            && $parts[0] === 'src' && $parts[1] === 'components'
            && $parts[2] === 'diy' && $parts[3] === 'plugins';
    }

    private function shouldSkip(string $relativePath): bool
    {
        $parts = explode('/', $relativePath);
        $first = $parts[0] ?? '';
        if (in_array($first, self::SKIP_DIRS, true)) {
            return true;
        }
        $basename = basename($relativePath);
        if (in_array($basename, self::SKIP_FILES, true)) {
            return true;
        }
        return false;
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
            throw new \RuntimeException("failed to mkdir {$dst}");
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iter as $item) {
            $relative = substr($item->getPathname(), strlen($src) + 1);
            $dstPath = $dst . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($dstPath) && !mkdir($dstPath, 0775, true) && !is_dir($dstPath)) {
                    throw new \RuntimeException("failed to mkdir {$dstPath}");
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
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
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
