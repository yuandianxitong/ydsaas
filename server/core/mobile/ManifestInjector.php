<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * 按租户配置改写 manifest.json：
 *   - name = mobileConfig.app_name（若设置）
 *   - appid = mobileConfig.wechat_appid（mp-weixin 平台）
 *
 * 写回到 $uniappDir/src/manifest.json。
 *
 * @return array<string, mixed> 改写后的 manifest 内容
 */
final class ManifestInjector
{
    /**
     * @param array<string, mixed> $mobileConfig 由 TenantMobileConfigService::get() 返回
     */
    public function inject(string $uniappDir, array $mobileConfig, string $platform): array
    {
        $manifestPath = $uniappDir . '/src/manifest.json';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException("manifest.json not found at {$manifestPath}");
        }
        // manifest.json 可能带注释（jsonc）；先剥注释再 decode。
        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            throw new \RuntimeException("failed to read {$manifestPath}");
        }
        $clean = preg_replace('#//[^\n]*|/\*.*?\*/#s', '', $raw) ?? $raw;
        $manifest = json_decode($clean, true);
        if (!is_array($manifest)) {
            throw new \RuntimeException('manifest.json is not valid JSON (after stripping comments)');
        }

        $appName     = (string) ($mobileConfig['app_name'] ?? '');
        $wechatAppId = (string) ($mobileConfig['wechat_appid'] ?? '');

        if ($appName !== '') {
            $manifest['name'] = $appName;
        }
        if ($platform === 'mp-weixin' && $wechatAppId !== '') {
            $manifest['mp-weixin'] = is_array($manifest['mp-weixin'] ?? null) ? $manifest['mp-weixin'] : [];
            $manifest['mp-weixin']['appid'] = $wechatAppId;
        }

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('failed to encode manifest.json');
        }
        file_put_contents($manifestPath, $encoded . "\n");

        return $manifest;
    }
}
