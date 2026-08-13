<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\runtime;

/**
 * 统一管理 server 下需可写的 runtime / storage 目录。
 *
 * 解决宝塔等环境：目录被 root 以 755 创建后，PHP-FPM（www）无法再 mkdir/写日志。
 * 供 web 安装器、saas:install、saas:ensure-runtime、业务代码共用。
 */
final class RuntimePaths
{
    public const DIR_MODE = 0775;
    public const KEYS_MODE = 0700;

    /**
     * 相对 server 根的目录列表（与安装器对齐）。
     *
     * @return list<string>
     */
    public static function relativeDirs(): array
    {
        return [
            'runtime/cache',
            'runtime/log',
            'runtime/temp',
            'runtime/session',
            'runtime/mobile-builds',
            'runtime/mobile-builds/_keys',
            'runtime/plugin-packages',
            'runtime/skin-packages',
            'public/storage',
            'public/storage/uploads',
            'public/storage/uploads/images',
            'public/storage/uploads/files',
            'public/storage/uploads/docs',
        ];
    }

    /** 插件 zip 备份 / plugin:pack 默认输出目录。 */
    public static function pluginPackagesDir(string $rootPath): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/plugin-packages';
    }

    /** 官方/本地皮肤 zip 目录（listOfficial 扫描）。 */
    public static function skinPackagesDir(string $rootPath): string
    {
        return rtrim($rootPath, '/\\') . '/runtime/skin-packages';
    }

    /**
     * 解析 plugins.package_path：文件仍在原处则原样返回；
     * 若已迁到 runtime/plugin-packages，则把旧 plugin_packages 段替换后返回。
     */
    public static function resolvePluginPackagePath(string $storedPath): string
    {
        if ($storedPath !== '' && is_file($storedPath)) {
            return $storedPath;
        }
        $legacy = DIRECTORY_SEPARATOR . 'plugin_packages' . DIRECTORY_SEPARATOR;
        $current = DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'plugin-packages' . DIRECTORY_SEPARATOR;
        if ($storedPath !== '' && str_contains($storedPath, $legacy)) {
            $candidate = str_replace($legacy, $current, $storedPath);
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $storedPath;
    }

    /**
     * 创建缺失目录、统一 chmod；若当前为 root 且传入 $webUser 则尝试 chown。
     *
     * @return array{
     *     created: list<string>,
     *     chowned: list<string>,
     *     not_writable: list<string>,
     *     errors: list<string>
     * }
     */
    public static function ensure(string $rootPath, ?string $webUser = null): array
    {
        $root = rtrim($rootPath, '/\\') . '/';
        $created = [];
        $chowned = [];
        $notWritable = [];
        $errors = [];

        $uid = null;
        $gid = null;
        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
        if ($isRoot && $webUser !== null && $webUser !== '' && function_exists('posix_getpwnam')) {
            $pw = @posix_getpwnam($webUser);
            if (is_array($pw)) {
                $uid = (int) $pw['uid'];
                $gid = (int) $pw['gid'];
            } else {
                $errors[] = "用户不存在或无法解析：{$webUser}（跳过 chown）";
            }
        }

        foreach (self::relativeDirs() as $rel) {
            $path = $root . $rel;
            if (!is_dir($path)) {
                if (!@mkdir($path, self::DIR_MODE, true) && !is_dir($path)) {
                    $errors[] = "无法创建目录：{$rel}";
                    $notWritable[] = $rel;
                    continue;
                }
                $created[] = $rel;
            }

            // 私钥目录更严；其余 0775 便于 www 组写
            $mode = str_ends_with($rel, '_keys') ? self::KEYS_MODE : self::DIR_MODE;
            if (!@chmod($path, $mode)) {
                // chmod 失败不阻断；最终以 is_writable 为准
            }

            if ($uid !== null && $gid !== null) {
                if (@chown($path, $uid) && @chgrp($path, $gid)) {
                    $chowned[] = $rel;
                }
            }

            // 对当前进程检测可写；root 下 chown 给 www 后 root 仍可写，www 需另验
            if (!is_writable($path)) {
                $notWritable[] = $rel;
            }
        }

        return [
            'created'      => $created,
            'chowned'      => $chowned,
            'not_writable' => array_values(array_unique($notWritable)),
            'errors'       => $errors,
        ];
    }

    /**
     * 确保私钥临时目录存在且（对当前进程）可写，返回绝对路径。
     *
     * @throws \RuntimeException
     */
    public static function ensureKeysDir(string $rootPath, ?string $webUser = null): string
    {
        $result = self::ensure($rootPath, $webUser);
        $dir = rtrim($rootPath, '/\\') . '/runtime/mobile-builds/_keys';
        @chmod($dir, self::KEYS_MODE);

        if (!is_dir($dir) || !is_writable($dir)) {
            $hint = '请在 server 目录执行：php think saas:ensure-runtime --user=www'
                . '（或 chown -R www:www runtime && chmod -R 775 runtime）';
            $detail = $result['not_writable'] !== []
                ? '不可写：' . implode(', ', $result['not_writable']) . '。'
                : '';
            throw new \RuntimeException("无法使用私钥临时目录 {$dir}。{$detail}{$hint}");
        }

        return $dir;
    }
}
