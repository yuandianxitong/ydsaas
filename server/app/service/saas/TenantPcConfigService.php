<?php

declare(strict_types=1);

namespace app\service\saas;

use app\repository\plugin\PluginRepository;
use app\repository\saas\TenantPcConfigRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\PluginScanner;

final class TenantPcConfigService extends Service
{
    protected TenantPcConfigRepository $repository;
    protected EntitlementService $entitlementService;
    protected PluginRepository $pluginRepository;
    protected PluginScanner $pluginScanner;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $diskManifests = null;

    private const HEX_COLOR_REGEX = '/^#[0-9a-fA-F]{6}$/';
    private const HOME_TYPES = ['diy', 'app', 'redirect'];

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'site_name' => '',
            'site_logo' => '',
            'site_intro' => '',
            'theme_color' => '#2563eb',
            'home_type' => 'diy',
            'home_app_code' => '',
            'home_page' => 'home',
            'nav' => [],
            'seo' => (object) [],
            'login_enabled' => true,
            'register_enabled' => true,
            'status' => 1,
            'fallback' => ['type' => 'diy', 'page_key' => 'home'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $tenantId): array
    {
        $row = $this->repository->findByTenantId($tenantId);
        if (!$row) {
            return $this->defaults();
        }

        $cfg = [
            'site_name' => (string) ($row['site_name'] ?? ''),
            'site_logo' => (string) ($row['site_logo'] ?? ''),
            'site_intro' => (string) ($row['site_intro'] ?? ''),
            'theme_color' => (string) (($row['theme_color'] ?? '') ?: '#2563eb'),
            'home_type' => $this->normalizeHomeType((string) (($row['home_type'] ?? '') ?: 'diy')),
            'home_app_code' => (string) ($row['home_app_code'] ?? ''),
            'home_page' => (string) (($row['home_page'] ?? '') ?: 'home'),
            'nav' => $this->decodeJson($row['nav_json'] ?? null),
            'seo' => (object) $this->decodeJson($row['seo_json'] ?? null),
            'login_enabled' => (bool) ($row['login_enabled'] ?? true),
            'register_enabled' => (bool) ($row['register_enabled'] ?? true),
            'status' => (int) ($row['status'] ?? 1),
            'fallback' => ['type' => 'diy', 'page_key' => 'home'],
        ];

        $cfg = $this->normalizeAppHomeRoute($tenantId, $cfg);
        return $this->filterByEntitlements($tenantId, $cfg);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(int $tenantId, array $input): array
    {
        $patch = [];

        foreach (['site_name', 'site_logo', 'site_intro'] as $field) {
            if (array_key_exists($field, $input)) {
                $patch[$field] = (string) $input[$field];
            }
        }

        if (array_key_exists('theme_color', $input)) {
            $color = (string) $input['theme_color'];
            if ($color !== '' && !preg_match(self::HEX_COLOR_REGEX, $color)) {
                throw new BusinessException("theme_color 必须为 6 位十六进制色值：{$color}", 422);
            }
            $patch['theme_color'] = $color ?: '#2563eb';
        }

        if (array_key_exists('home_type', $input)) {
            $homeType = (string) $input['home_type'];
            if (!in_array($homeType, self::HOME_TYPES, true)) {
                throw new BusinessException("home_type 非法：{$homeType}", 422);
            }
            $patch['home_type'] = $homeType;
        }
        if (array_key_exists('home_app_code', $input)) {
            $patch['home_app_code'] = (string) $input['home_app_code'];
        }
        if (array_key_exists('home_page', $input)) {
            $patch['home_page'] = (string) $input['home_page'];
        }

        if (array_key_exists('nav', $input)) {
            $patch['nav_json'] = $this->validateNav($tenantId, is_array($input['nav']) ? $input['nav'] : []);
        }
        if (array_key_exists('seo', $input)) {
            $patch['seo_json'] = is_array($input['seo']) ? $input['seo'] : [];
        }
        if (array_key_exists('login_enabled', $input)) {
            $patch['login_enabled'] = (bool) $input['login_enabled'] ? 1 : 0;
        }
        if (array_key_exists('register_enabled', $input)) {
            $patch['register_enabled'] = (bool) $input['register_enabled'] ? 1 : 0;
        }
        if (array_key_exists('status', $input)) {
            $patch['status'] = (int) $input['status'] === 0 ? 0 : 1;
        }

        $merged = $this->normalizeAppHomeRoute($tenantId, array_merge($this->get($tenantId), $input));
        $this->assertHome($tenantId, $merged);
        if (array_intersect(['home_type', 'home_app_code', 'home_page'], array_keys($input)) !== []) {
            $patch['home_type'] = (string) $merged['home_type'];
            $patch['home_app_code'] = (string) $merged['home_app_code'];
            $patch['home_page'] = (string) $merged['home_page'];
        }
        $this->repository->upsert($tenantId, $patch);

        return $this->get($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(int $tenantId): array
    {
        $plugins = [];
        foreach ($this->listPcPlugins($tenantId) as $plugin) {
            $pc = $this->pcManifest($plugin);
            $pages = [];
            foreach ((array) ($pc['pages'] ?? []) as $page) {
                $pages[] = [
                    'route' => (string) ($page['route'] ?? ''),
                    'title' => (string) ($page['title'] ?? ''),
                    'nav' => (bool) ($page['nav'] ?? false),
                    'auth' => (bool) ($page['auth'] ?? false),
                ];
            }
            $plugins[] = [
                'code' => (string) ($plugin['entitlement'] ?? $plugin['code']),
                'plugin_code' => (string) $plugin['code'],
                'name' => (string) ($plugin['name'] ?? $plugin['code']),
                'kind' => (string) ($plugin['kind'] ?? 'plugin'),
                'allowHome' => (bool) ($pc['allowHome'] ?? false),
                'home' => (string) ($pc['home'] ?? ''),
                'pages' => $pages,
            ];
        }

        return [
            'homeOptions' => array_values(array_filter($plugins, fn ($p) => $p['allowHome'])),
            'navOptions' => $plugins,
            'fallback' => ['type' => 'diy', 'page_key' => 'home'],
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    private function filterByEntitlements(int $tenantId, array $cfg): array
    {
        $homeType = (string) ($cfg['home_type'] ?? 'diy');
        $homeCode = (string) ($cfg['home_app_code'] ?? '');
        $homePage = (string) ($cfg['home_page'] ?? '');
        if ($homeType === 'app'
            && !$this->isAllowedPluginRoute($tenantId, $homeCode, $homePage, true)) {
            $cfg['home_type'] = 'diy';
            $cfg['home_app_code'] = '';
            $cfg['home_page'] = 'home';
        }

        $cfg['nav'] = array_values(array_filter(
            is_array($cfg['nav'] ?? null) ? $cfg['nav'] : [],
            fn ($item) => is_array($item)
                && (($item['code'] ?? '') === ''
                    || $this->isAllowedPluginRoute($tenantId, (string) $item['code'], (string) ($item['path'] ?? ''), false))
        ));

        return $cfg;
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private function assertHome(int $tenantId, array $cfg): void
    {
        $homeType = (string) ($cfg['home_type'] ?? 'diy');
        if ($homeType === 'diy' || $homeType === 'redirect') {
            return;
        }
        $code = (string) ($cfg['home_app_code'] ?? '');
        $path = (string) ($cfg['home_page'] ?? '');
        if (!$this->isAllowedPluginRoute($tenantId, $code, $path, true)) {
            throw new BusinessException("当前租户不可使用该 PC 首页：{$code} {$path}", 422);
        }
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function validateNav(int $tenantId, array $items): array
    {
        $out = [];
        foreach (array_values($items) as $i => $item) {
            if (!is_array($item)) {
                throw new BusinessException("nav[{$i}] 必须是对象", 422);
            }
            $label = trim((string) ($item['label'] ?? ''));
            $path = trim((string) ($item['path'] ?? ''));
            $code = trim((string) ($item['code'] ?? ''));
            if ($label === '' || $path === '') {
                throw new BusinessException("nav[{$i}] 缺少 label/path", 422);
            }
            if ($code !== '' && !$this->isAllowedPluginRoute($tenantId, $code, $path, false)) {
                throw new BusinessException("nav[{$i}] 不属于当前租户可用 PC 页面：{$code} {$path}", 422);
            }
            $out[] = [
                'label' => $label,
                'path' => $path,
                'code' => $code,
                'auth' => (bool) ($item['auth'] ?? false),
                'sort' => (int) ($item['sort'] ?? ($i + 1)),
            ];
        }
        usort($out, fn ($a, $b) => ($a['sort'] <=> $b['sort']));
        return $out;
    }

    private function isAllowedPluginRoute(int $tenantId, string $code, string $route, bool $homeOnly): bool
    {
        if ($code === '' || $route === '') {
            return false;
        }
        if (!$this->entitlementService->has($tenantId, $code)) {
            return false;
        }
        $plugin = $this->pluginRepository->findByEntitlement($code) ?? $this->pluginRepository->findByCode($code);
        if (!$plugin) {
            return false;
        }
        $pc = $this->pcManifest($plugin);
        if ($pc === []) {
            return false;
        }
        if ($homeOnly && !($pc['allowHome'] ?? false)) {
            return false;
        }
        foreach ((array) ($pc['pages'] ?? []) as $page) {
            if ((string) ($page['route'] ?? '') === $route) {
                return true;
            }
        }
        return $homeOnly && (string) ($pc['home'] ?? '') === $route;
    }

    private function normalizeHomeType(string $homeType): string
    {
        return in_array($homeType, ['cms', 'plugin'], true) ? 'app' : $homeType;
    }

    /**
     * 兼容旧版 DB manifest 中把插件首页声明为 "/" 的数据。
     * 当前 PC 架构中 "/" 由平台默认首页占用，应用首页应落在插件自己的路由下。
     *
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    private function normalizeAppHomeRoute(int $tenantId, array $cfg): array
    {
        if (($cfg['home_type'] ?? 'diy') !== 'app') {
            return $cfg;
        }
        $code = (string) ($cfg['home_app_code'] ?? '');
        $route = (string) ($cfg['home_page'] ?? '');
        if ($code === '' || $route !== '/') {
            return $cfg;
        }

        $plugin = $this->pluginRepository->findByEntitlement($code) ?? $this->pluginRepository->findByCode($code);
        if (!$plugin || !$this->entitlementService->has($tenantId, $code)) {
            return $cfg;
        }
        $pc = $this->pcManifest($plugin);
        $home = (string) ($pc['home'] ?? '');
        if ($home !== '' && $home !== '/') {
            $cfg['home_page'] = $home;
        }
        return $cfg;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listPcPlugins(int $tenantId): array
    {
        $out = [];
        foreach ($this->entitlementService->list($tenantId) as $entitlement) {
            $plugin = $this->pluginRepository->findByEntitlement($entitlement->code)
                ?? $this->pluginRepository->findByCode($entitlement->code);
            if ($plugin && $this->pcManifest($plugin) !== []) {
                $out[] = $plugin;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $plugin
     * @return array<string, mixed>
     */
    private function pcManifest(array $plugin): array
    {
        $code = (string) ($plugin['code'] ?? '');
        if ($code !== '') {
            $this->diskManifests ??= $this->pluginScanner->scan();
            $diskManifest = $this->diskManifests[$code] ?? null;
            if (is_array($diskManifest['pc'] ?? null)) {
                return $diskManifest['pc'];
            }
        }

        $manifest = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);
        return is_array($manifest['pc'] ?? null) ? $manifest['pc'] : [];
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
