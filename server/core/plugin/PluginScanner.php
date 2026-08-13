<?php
/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use think\facade\App;
use think\facade\Log;

/**
 * 扫 server/plugins/* 目录，把所有 manifest 合法的插件抓出来。
 *
 * 用途：让管理端"应用管理"列表能合并展示「磁盘里有但 DB 没登记」的插件，
 * 也作为 PluginPackageService::registerFromDisk() 的数据源。
 *
 * 设计：
 *   - 只关心 plugin.json 解析 + PluginManifestValidator 校验；不碰 DB
 *   - manifest 不合法的项跳过 + warning log（避免坏插件污染列表）
 *   - 顶层目录 . / .. / 非目录 / 缺 plugin.json 都静默跳过
 */
class PluginScanner
{
    public function __construct(
        private PluginManifestValidator $validator,
        private ?string $pluginsDir = null,
    ) {
        $this->pluginsDir = $pluginsDir ?? rtrim(App::getRootPath(), '/') . '/plugins';
    }

    /**
     * @return array<string, array<string, mixed>> code => manifest
     */
    public function scan(): array
    {
        if (!is_dir($this->pluginsDir)) {
            return [];
        }
        $entries = @scandir($this->pluginsDir) ?: [];
        $result = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $this->pluginsDir . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $manifestFile = $dir . '/plugin.json';
            if (!is_file($manifestFile)) {
                continue;
            }
            $raw = @file_get_contents($manifestFile);
            if ($raw === false) {
                Log::warning("[plugin-scanner] cannot read {$manifestFile}");
                continue;
            }
            $manifest = json_decode($raw, true);
            if (!is_array($manifest)) {
                Log::warning("[plugin-scanner] invalid json: {$manifestFile}");
                continue;
            }
            $errors = $this->validator->validate($manifest);
            if ($errors !== []) {
                Log::warning("[plugin-scanner] manifest 校验失败 {$entry}: " . implode('; ', $errors));
                continue;
            }
            $code = (string) $manifest['code'];
            if ($code === '') {
                continue;
            }
            $result[$code] = $manifest;
        }
        return $result;
    }
}
