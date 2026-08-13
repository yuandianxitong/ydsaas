<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * 写 src/generated/tenant-config.ts —— UniApp 首屏兜底配置（home_page / theme / tabbar / home_decoration）。
 *
 * 与 /api/mobile/config 的关系：
 *   - 构建期注入：独立产物首屏可用，弱网/离线兜底
 *   - 运行时 fetch：store.load() 始终拉取并覆盖软字段（装修/主题/tabBar/启动入口等）
 *   - 因此本 Writer 仍须写全 C 端渲染所需字段，避免首屏闪空；结构变更（页面集合）仍靠构建。
 */
final class TenantConfigWriter
{
    /**
     * @param array<string, mixed> $mobileConfig TenantMobileConfigService::get() 形态
     */
    public function write(string $uniappDir, int $tenantId, string $tenantCode, array $mobileConfig): string
    {
        $dir = $uniappDir . '/src/generated';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("failed to mkdir {$dir}");
        }

        $tabbar = is_array($mobileConfig['tabbar'] ?? null) ? $mobileConfig['tabbar'] : [];
        $tabbarStyle = is_array($mobileConfig['tabbar_style'] ?? null) ? $mobileConfig['tabbar_style'] : [];
        $themeColors = is_array($mobileConfig['theme_colors'] ?? null) ? $mobileConfig['theme_colors'] : [];
        $homeDecoration = $mobileConfig['home_decoration'] ?? null;
        if ($homeDecoration !== null && !is_array($homeDecoration)) {
            $homeDecoration = null;
        }

        $payload = [
            'tenantId'        => $tenantId,
            'tenantCode'      => $tenantCode,
            'appName'         => (string) ($mobileConfig['app_name'] ?? ''),
            'appLogo'         => (string) ($mobileConfig['app_logo'] ?? ''),
            'appIntro'        => (string) ($mobileConfig['app_intro'] ?? ''),
            'themeColor'      => (string) ($mobileConfig['theme_color'] ?? ''),
            'themeColors'     => $themeColors,
            'serviceType'     => (string) ($mobileConfig['service_type'] ?? ''),
            'servicePhone'    => (string) ($mobileConfig['service_phone'] ?? ''),
            'shareTitle'      => (string) ($mobileConfig['share_title'] ?? ''),
            'shareImage'      => (string) ($mobileConfig['share_image'] ?? ''),
            'homePage'        => (string) ($mobileConfig['home_page'] ?? ''),
            'homeAppCode'     => (string) ($mobileConfig['home_app_code'] ?? ''),
            'tabbar'          => $tabbar,
            'tabbarStyle'     => $tabbarStyle,
            'homeDecoration'  => $homeDecoration,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('failed to encode tenant-config payload');
        }

        $body = "// AUTO-GENERATED, do not edit by hand.\n"
            . "// Injected at build time for tenant={$tenantCode} (#{$tenantId}).\n"
            . "// See server/core/mobile/TenantConfigWriter.php\n\n"
            . 'export const tenantConfig = ' . $json . " as const\n"
            . "export type TenantConfig = typeof tenantConfig\n";

        $path = $dir . '/tenant-config.ts';
        file_put_contents($path, $body);

        // #region agent log
        $__hd = $payload['homeDecoration'];
        $__log = [
            'sessionId' => 'ddd612',
            'hypothesisId' => 'B',
            'location' => 'TenantConfigWriter.php:write',
            'message' => 'tenant-config.ts written',
            'data' => [
                'path' => $path,
                'tenantId' => $tenantId,
                'tenantCode' => $tenantCode,
                'payloadKeys' => array_keys($payload),
                'tabbarCount' => count($payload['tabbar']),
                'hasHomeDecorationKey' => array_key_exists('homeDecoration', $payload),
                'homeDecorationNull' => $__hd === null,
                'homeDecorationComponents' => is_array($__hd) ? count($__hd['components'] ?? []) : 0,
                'bodyHasHomeDecoration' => str_contains($body, 'homeDecoration'),
                'bodyHasTabbarStyle' => str_contains($body, 'tabbarStyle'),
            ],
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        $__logPath = dirname(__DIR__, 3) . '/.cursor/debug-ddd612.log';
        if (!is_dir(dirname($__logPath))) {
            @mkdir(dirname($__logPath), 0775, true);
        }
        @file_put_contents($__logPath, json_encode($__log, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        // #endregion

        return $path;
    }
}
