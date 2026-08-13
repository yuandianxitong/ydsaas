<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\diy\skin;

use core\exception\BusinessException;
use ZipArchive;

/**
 * 皮肤包 ZIP 读写（路径安全）。
 */
final class SkinZip
{
    public const MAX_SIZE = 80 * 1024 * 1024;
    public const ASSET_PREFIX = '__SKIN_ASSET__/';

    private const ALLOWED_TOP = ['skin.json', 'mobile.json', 'pages/', 'assets/'];

    /**
     * @return array{
     *   dir: string,
     *   manifest: array<string,mixed>,
     *   mobile: array<string,mixed>,
     *   pages: array<string,array{platform:string,page_key:string,title:string,page_type:string,components:array,page_settings:array}>,
     *   assets_dir: string
     * }
     */
    public function extractToTemp(string $zipPath): array
    {
        if (!is_file($zipPath)) {
            throw new BusinessException('皮肤包文件不存在', 422);
        }
        if (filesize($zipPath) > self::MAX_SIZE) {
            throw new BusinessException('皮肤包超过 80MB 限制', 422);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new BusinessException('皮肤包无法打开（可能损坏）', 422);
        }

        try {
            $this->assertSafeEntries($zip);
            $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'skin_' . bin2hex(random_bytes(8));
            if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
                throw new BusinessException('无法创建临时目录', 500);
            }
            if (!$zip->extractTo($dir)) {
                throw new BusinessException('皮肤包解压失败', 500);
            }
        } finally {
            $zip->close();
        }

        $manifestPath = $dir . '/skin.json';
        $mobilePath = $dir . '/mobile.json';
        if (!is_file($manifestPath) || !is_file($mobilePath)) {
            $this->removeDir($dir);
            throw new BusinessException('皮肤包缺少 skin.json 或 mobile.json', 422);
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $mobile = json_decode((string) file_get_contents($mobilePath), true);
        if (!is_array($manifest) || !is_array($mobile)) {
            $this->removeDir($dir);
            throw new BusinessException('skin.json / mobile.json 不是合法 JSON', 422);
        }

        $errors = (new SkinManifestValidator())->validate($manifest);
        if ($errors !== []) {
            $this->removeDir($dir);
            throw new BusinessException('皮肤包清单无效：' . implode('；', $errors), 422);
        }

        $pages = $this->loadPages($dir, $manifest);

        return [
            'dir'        => $dir,
            'manifest'   => $manifest,
            'mobile'     => $mobile,
            'pages'      => $pages,
            'assets_dir' => $dir . '/assets',
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,mixed> $mobile
     * @param array<string,array<string,mixed>> $pageFiles key = "uniapp/home" 等相对路径（无 .json）
     * @param array<string,string> $assetFiles filename => absolute path
     */
    public function build(string $targetZip, array $manifest, array $mobile, array $pageFiles, array $assetFiles = []): void
    {
        $errors = (new SkinManifestValidator())->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('皮肤包清单无效：' . implode('；', $errors), 422);
        }

        $dir = dirname($targetZip);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new BusinessException('无法创建导出目录', 500);
        }

        if (is_file($targetZip)) {
            @unlink($targetZip);
        }

        $zip = new ZipArchive();
        if ($zip->open($targetZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BusinessException('无法创建皮肤包', 500);
        }

        try {
            $zip->addFromString('skin.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $zip->addFromString('mobile.json', json_encode($mobile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            foreach ($pageFiles as $rel => $payload) {
                $rel = ltrim(str_replace('\\', '/', $rel), '/');
                if (!str_starts_with($rel, 'pages/') || str_contains($rel, '..')) {
                    throw new BusinessException("非法页面路径：{$rel}", 422);
                }
                $zip->addFromString(
                    str_ends_with($rel, '.json') ? $rel : $rel . '.json',
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                );
            }
            foreach ($assetFiles as $name => $abs) {
                $name = basename(str_replace('\\', '/', (string) $name));
                if ($name === '' || !is_file($abs)) {
                    continue;
                }
                $zip->addFile($abs, 'assets/' . $name);
            }
        } finally {
            $zip->close();
        }
    }

    public function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function assertSafeEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $name = ltrim($name, '/');
            if ($name === '' || str_contains($name, "\0") || str_contains($name, '..')) {
                throw new BusinessException('皮肤包含非法路径', 422);
            }
            $ok = false;
            foreach (self::ALLOWED_TOP as $top) {
                if ($name === rtrim($top, '/') || str_starts_with($name, $top)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                throw new BusinessException("皮肤包含未允许条目：{$name}", 422);
            }
        }
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,array{platform:string,page_key:string,title:string,page_type:string,components:array,page_settings:array}>
     */
    private function loadPages(string $dir, array $manifest): array
    {
        $out = [];
        $platforms = (array) ($manifest['platforms'] ?? ['uniapp']);
        $pageKeys = (array) ($manifest['pages'] ?? []);

        foreach ($platforms as $platform) {
            $platform = (string) $platform;
            if ($platform === 'pc') {
                $keys = ['home'];
            } else {
                $keys = $pageKeys;
            }
            foreach ($keys as $pageKey) {
                $pageKey = (string) $pageKey;
                $rel = "pages/{$platform}/{$pageKey}.json";
                $path = $dir . '/' . $rel;
                if (!is_file($path)) {
                    if ($platform === 'pc') {
                        continue; // PC 首页可选
                    }
                    throw new BusinessException("皮肤包缺少页面：{$rel}", 422);
                }
                $data = json_decode((string) file_get_contents($path), true);
                if (!is_array($data)) {
                    throw new BusinessException("页面 JSON 无效：{$rel}", 422);
                }
                $components = $data['components'] ?? [];
                $settings = $data['page_settings'] ?? [];
                if (!is_array($components) || !is_array($settings)) {
                    throw new BusinessException("页面结构无效：{$rel}", 422);
                }
                $out["{$platform}/{$pageKey}"] = [
                    'platform'      => $platform,
                    'page_key'      => $pageKey,
                    'title'         => (string) ($data['title'] ?? $pageKey),
                    'page_type'     => (string) ($data['page_type'] ?? ($pageKey === 'home' || $pageKey === 'member' ? $pageKey : 'custom')),
                    'components'    => $components,
                    'page_settings' => $settings,
                ];
            }
        }

        return $out;
    }
}
