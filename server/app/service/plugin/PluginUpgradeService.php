<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\model\plugin\Plugin;
use app\repository\plugin\PluginRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\contracts\LifecycleHook;
use core\plugin\PluginManifestValidator;
use core\plugin\PluginPackageInstaller;
use core\plugin\PluginRegistry;
use core\plugin\PluginSqlRunner;
use core\runtime\RuntimePaths;
use think\facade\App;

/**
 * 插件升级：原位替换 zip + 跑升级 migration + lifecycle.upgrade。
 * 失败回滚到 .backup 旧版。
 */
class PluginUpgradeService extends Service
{
    protected PluginRepository $pluginRepo;
    protected PluginPackageInstaller $installer;
    protected PluginManifestValidator $manifestValidator;
    protected PluginSqlRunner $sqlRunner;
    protected PluginRegistry $registry;

    public static function isUpgrade(string $from, string $to): bool
    {
        return version_compare($to, $from, '>');
    }

    public static function backupDirName(string $code, string $version): string
    {
        return "{$code}-{$version}-" . time();
    }

    /**
     * @return array<string, mixed>
     */
    public function upgrade(int $pluginId, string $tmpZipPath): array
    {
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException('插件不存在', 404);
        }
        if ((int) $plugin['status'] !== Plugin::STATUS_ENABLED) {
            throw new BusinessException('插件当前状态不可升级（必须处于已启用）', 409);
        }

        $code = (string) $plugin['code'];
        $fromVersion = (string) $plugin['version'];

        // 1. dry-run inspect + 校验清单
        $manifest = $this->installer->inspect($tmpZipPath);
        $errors = $this->manifestValidator->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('plugin.json 校验失败: ' . implode('; ', $errors), 422);
        }
        if ((string) $manifest['code'] !== $code) {
            throw new BusinessException("升级 zip 的 code [{$manifest['code']}] 与原插件 [{$code}] 不匹配", 422);
        }
        $toVersion = (string) $manifest['version'];
        if (!self::isUpgrade($fromVersion, $toVersion)) {
            throw new BusinessException("新版本 {$toVersion} 不高于旧版本 {$fromVersion}", 422);
        }

        $rootPath = App::getRootPath();
        $pluginsDir = $rootPath . 'plugins';
        $pluginDir = $pluginsDir . '/' . $code;
        $backupDir = $pluginsDir . '/.backup/' . self::backupDirName($code, $fromVersion);

        $this->pluginRepo->update($pluginId, ['status' => Plugin::STATUS_UPGRADING]);

        try {
            // 2. 备份旧版
            if (!is_dir($pluginsDir . '/.backup')) {
                mkdir($pluginsDir . '/.backup', 0o755, true);
            }
            if (is_dir($pluginDir)) {
                if (!rename($pluginDir, $backupDir)) {
                    throw new BusinessException('备份旧版失败', 500);
                }
            }

            // 3. 解压新版
            $this->installer->extract($tmpZipPath, $pluginDir);

            // 4. 跑升级 migration —— v2.7.0：状态表感知，按 vX.Y.Z_ 前缀 + 状态表去重
            $this->runInTransaction(function () use ($pluginDir, $pluginId, $code, $fromVersion, $toVersion) {
                $this->sqlRunner->upgrade($pluginDir, $pluginId, $code, $fromVersion, $toVersion);
            });

            // 5. 调 Lifecycle.upgrade
            $this->invokeUpgradeHook($manifest, $fromVersion, $toVersion);

            // 6. 备份 zip + 更新 plugins 行
            $packageDir = RuntimePaths::pluginPackagesDir($rootPath);
            $packagePath = $packageDir . '/' . $code . '-' . $toVersion . '.zip';
            if (!is_dir($packageDir)) {
                mkdir($packageDir, RuntimePaths::DIR_MODE, true);
            }
            copy($tmpZipPath, $packagePath);

            $this->pluginRepo->update($pluginId, [
                'version' => $toVersion,
                'name' => (string) ($manifest['name'] ?? $code),
                'description' => (string) ($manifest['description'] ?? ''),
                'manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
                'requires' => isset($manifest['requires']) ? json_encode($manifest['requires']) : null,
                // Task 17: 升级时也同步 manifest.read_safe_routes，新版可能新增 / 移除
                // 豁免路由；缺失或非数组重置为 NULL（与 install 路径一致语义）。
                'read_safe_routes' => isset($manifest['read_safe_routes']) && is_array($manifest['read_safe_routes'])
                    ? json_encode(array_values($manifest['read_safe_routes']), JSON_UNESCAPED_UNICODE)
                    : null,
                'package_path' => $packagePath,
                'package_hash' => hash_file('sha256', $packagePath) ?: '',
                'status' => Plugin::STATUS_ENABLED,
                'last_error' => '',
            ]);

            $this->registry->clear();

            if (is_dir($backupDir)) {
                $this->rrmdir($backupDir);
            }

            return ['id' => $pluginId, 'from' => $fromVersion, 'to' => $toVersion, 'status' => Plugin::STATUS_ENABLED];
        } catch (\Throwable $e) {
            if (is_dir($pluginDir)) {
                $this->rrmdir($pluginDir);
            }
            if (is_dir($backupDir)) {
                rename($backupDir, $pluginDir);
            }
            $this->pluginRepo->update($pluginId, [
                'status' => Plugin::STATUS_ENABLED,
                'last_error' => substr($e->getMessage(), 0, 1000),
            ]);
            throw new BusinessException('插件升级失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function invokeUpgradeHook(array $manifest, string $from, string $to): void
    {
        $cls = (string) ($manifest['lifecycle'] ?? '');
        if ($cls === '' || !class_exists($cls)) {
            return;
        }
        $i = new $cls();
        if (!$i instanceof LifecycleHook) {
            throw BusinessException::pluginRuntimeError(
                (string) ($manifest['code'] ?? '?'),
                'upgrade',
                new \RuntimeException('lifecycle 类未实现 LifecycleHook 接口')
            );
        }
        try {
            $i->upgrade($from, $to);
        } catch (\Throwable $e) {
            throw BusinessException::pluginRuntimeError(
                (string) ($manifest['code'] ?? '?'),
                'upgrade',
                $e
            );
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
