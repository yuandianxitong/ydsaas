<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\service\saas;

use app\repository\plugin\PluginRepository;
use core\base\Service;

/**
 * 列出当前租户「可作为首页 / 可作为 tabBar」的插件清单。
 *
 * 算法：
 *   1. 取租户当前权益 → entitlement codes
 *   2. 对每个 code 找对应 plugin 行，读 manifest.uniapp
 *   3. 判断：
 *      homeOptions    = kind=app（默认 allowHome=true） 或 manifest.uniapp.allowHome=true
 *      tabBarOptions  = kind=app（默认 allowTabBar=true）或 manifest.uniapp.allowTabBar=true
 *   4. 每条返回前端可消费的字段：{ code, name, subpackage, pages[], default_home_path }
 */
final class MobileEligibilityService extends Service
{
    protected EntitlementService $entitlementService;
    protected PluginRepository $pluginRepository;

    /**
     * @return array{homeOptions: array<int, array<string, mixed>>, tabBarOptions: array<int, array<string, mixed>>}
     */
    public function listEligible(int $tenantId): array
    {
        $homeOptions   = [];
        $tabBarOptions = [];

        foreach ($this->entitlementService->list($tenantId) as $ent) {
            $plugin = $this->findPluginByEntitlement($ent->code);
            if (!$plugin) {
                continue;
            }

            $manifest = $this->decodeManifest($plugin['manifest'] ?? null);
            $uniapp   = is_array($manifest['uniapp'] ?? null) ? $manifest['uniapp'] : [];

            $isApp        = ($plugin['kind'] ?? 'plugin') === 'app';
            $allowHome    = (bool) ($uniapp['allowHome']    ?? $isApp);
            $allowTabBar  = (bool) ($uniapp['allowTabBar']  ?? $isApp);

            // 没声明 uniapp 的插件直接跳过
            if (!isset($manifest['uniapp'])) {
                continue;
            }

            $entry = [
                'code'              => $ent->code,
                'name'              => (string) ($plugin['name'] ?? $ent->code),
                'kind'              => (string) ($plugin['kind'] ?? 'plugin'),
                'subpackage'        => (string) ($uniapp['subpackage'] ?? ''),
                'pages'             => is_array($uniapp['pages'] ?? null) ? $uniapp['pages'] : [],
                'default_home_path' => $this->guessDefaultHome($uniapp),
            ];

            if ($allowHome) {
                $homeOptions[]   = $entry;
            }
            if ($allowTabBar) {
                $tabBarOptions[] = $entry;
            }
        }

        // 内置（base 壳）页面：无需任何插件即可作为首页 / tabBar。
        // 让租户没装插件也能配置底部导航（首页=DIY 装修页，我的=用户中心）。
        $builtinHome = [
            'code'              => '__home__',
            'name'              => '首页',
            'kind'              => 'builtin',
            'subpackage'        => '',
            'pages'             => [['path' => 'pages/index/index']],
            'default_home_path' => 'pages/index/index',
        ];
        $builtinMy = [
            'code'              => '__my__',
            'name'              => '我的',
            'kind'              => 'builtin',
            'subpackage'        => '',
            'pages'             => [['path' => 'pages/my/index']],
            'default_home_path' => 'pages/my/index',
        ];

        return [
            'homeOptions'   => array_merge([$builtinHome], $homeOptions),
            'tabBarOptions' => array_merge([$builtinHome, $builtinMy], $tabBarOptions),
        ];
    }

    private function findPluginByEntitlement(string $entitlement): ?array
    {
        return $this->pluginRepository->findByEntitlement($entitlement)
            ?? $this->pluginRepository->findByCode($entitlement);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * 默认首页推断：manifest.uniapp.home 优先，否则 pages[0].path 拼上 subpackage 前缀。
     */
    private function guessDefaultHome(array $uniapp): string
    {
        if (!empty($uniapp['home'])) {
            return $this->prefixSubpackage($uniapp);
        }
        $pages = is_array($uniapp['pages'] ?? null) ? $uniapp['pages'] : [];
        if (empty($pages)) {
            return '';
        }
        $first = $pages[0]['path'] ?? '';
        if ($first === '') {
            return '';
        }
        $subpackage = (string) ($uniapp['subpackage'] ?? '');
        return $subpackage !== '' ? rtrim($subpackage, '/') . '/' . ltrim($first, '/') : $first;
    }

    private function prefixSubpackage(array $uniapp): string
    {
        $home       = (string) ($uniapp['home'] ?? '');
        $subpackage = (string) ($uniapp['subpackage'] ?? '');
        if ($subpackage === '' || str_starts_with($home, $subpackage)) {
            return $home;
        }
        return rtrim($subpackage, '/') . '/' . ltrim($home, '/');
    }
}
