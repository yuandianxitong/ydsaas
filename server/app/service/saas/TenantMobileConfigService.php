<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\service\saas;

use app\repository\plugin\PluginRepository;
use app\repository\saas\TenantMobileConfigRepository;
use app\service\diy\DiyPageService;
use core\base\Service;
use core\exception\BusinessException;

/**
 * 租户移动端配置：启动首页、主题色、Logo、tabBar。
 *
 * 写入时做两道校验：
 *   1. home_app_code 必须在租户当前权益内
 *   2. 每个 tabBar 项的 code 必须在权益内 + 对应 plugin 的 manifest.uniapp.allowTabBar 为 true
 *      （kind=app 默认 allowTabBar=true，kind=plugin 需显式声明）
 */
final class TenantMobileConfigService extends Service
{
    protected TenantMobileConfigRepository $repository;
    protected EntitlementService $entitlementService;
    protected PluginRepository $pluginRepository;
    protected DiyPageService $diyPageService;

    private const HEX_COLOR_REGEX = '/^#[0-9a-fA-F]{6}$/';

    /** 内置（base 壳）可作为首页 / tabBar 的页面：reserved code => path，免 entitlement。 */
    private const BUILTIN_PAGES = [
        '__home__' => 'pages/index/index',
        '__my__'   => 'pages/my/index',
    ];

    /** DIY 自定义页（pages/diy/index?key=slug）在 tabBar 中的 reserved code。 */
    private const DIY_CODE = '__diy__';

    /** JSON 列解码为数组（ThinkPHP 可能返回数组或字符串） */
    private function decodeJson(mixed $v): array
    {
        if (is_array($v)) {
            return $v;
        }
        if (is_string($v) && $v !== '') {
            $d = json_decode($v, true);
            return is_array($d) ? $d : [];
        }
        return [];
    }

    /** 校验颜色 map（key=>hex），非空值须匹配 HEX */
    private function assertColors(array $colors, string $field): void
    {
        foreach ($colors as $k => $v) {
            $v = (string) $v;
            if ($v !== '' && !preg_match(self::HEX_COLOR_REGEX, $v)) {
                throw new BusinessException("{$field}.{$k} 必须为 6 位十六进制色值：{$v}", 422);
            }
        }
    }

    /**
     * 默认空配置（前端 first-load 用）。
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'app_name'      => '',
            'app_logo'      => '',
            'app_intro'     => '',
            'theme_color'   => '',
            'theme_colors'  => (object) [],
            'service_type'  => '',
            'service_phone' => '',
            'share_title'   => '',
            'share_image'   => '',
            'home_app_code' => '',
            'home_page'     => '',
            'tabbar'        => [],
            'tabbar_style'  => (object) [],
            'wechat_appid'           => '',
            'wechat_upload_version'  => '1.0.0',
            'wechat_upload_desc'     => '租户后台发布',
            'status'                 => 1,
        ];
    }

    /**
     * 读取租户配置，无记录则返回默认值。
     *
     * v2.6.1：返回前按当前权益重新过滤 home_page / tabbar。
     * 场景：租户先配置了 mall 首页 + tabbar，后来套餐移除 mall；
     * 直接返回 DB 数据会让 C 端 / 构建期拿到无效配置（无源码、无权限）。
     * 失效项「软隐藏」：DB 不清，仅响应里去掉，便于租户重新拿到套餐后自动恢复。
     *
     * @return array<string, mixed>
     */
    public function get(int $tenantId): array
    {
        $row = $this->repository->findByTenantId($tenantId);
        if (!$row) {
            $config = $this->defaults();
            $config['home_decoration'] = $this->diyPageService->getPublishedHomeForTenant($tenantId);
            return $config;
        }
        $themeColors = $this->decodeJson($row['theme_colors'] ?? null);
        if (empty($themeColors['primary']) && ($row['theme_color'] ?? '') !== '') {
            $themeColors['primary'] = (string) $row['theme_color'];
        }
        $raw = [
            'app_name'      => (string) ($row['app_name'] ?? ''),
            'app_logo'      => (string) ($row['app_logo'] ?? ''),
            'app_intro'     => (string) ($row['app_intro'] ?? ''),
            'theme_color'   => (string) ($row['theme_color'] ?? ''),
            'theme_colors'  => $themeColors,
            'service_type'  => (string) ($row['service_type'] ?? ''),
            'service_phone' => (string) ($row['service_phone'] ?? ''),
            'share_title'   => (string) ($row['share_title'] ?? ''),
            'share_image'   => (string) ($row['share_image'] ?? ''),
            'home_app_code' => (string) ($row['home_app_code'] ?? ''),
            'home_page'     => (string) ($row['home_page'] ?? ''),
            // 与 tabbar_style 一致走 decodeJson：部分查询路径可能得到 JSON 字符串，
            // 仅 is_array 判断会静默变成 []，构建产物 tabBar 回落默认「首页/我的」。
            'tabbar'        => $this->decodeJson($row['tabbar_json'] ?? null),
            'tabbar_style'  => $this->decodeJson($row['tabbar_style'] ?? null),
            'wechat_appid'          => (string) ($row['wechat_appid'] ?? ''),
            'wechat_upload_version' => self::normalizeUploadVersion((string) ($row['wechat_upload_version'] ?? '')),
            'wechat_upload_desc'    => self::normalizeUploadDesc((string) ($row['wechat_upload_desc'] ?? '')),
            'status'               => (int) ($row['status'] ?? 1),
        ];
        $config = $this->filterByEntitlements($tenantId, $raw);
        $config['home_decoration'] = $this->diyPageService->getPublishedHomeForTenant($tenantId);
        return $config;
    }

    /**
     * 按租户当前权益 + 当前插件 manifest 过滤配置。剔除场景：
     *   - 失去权益（套餐变更 / 插件下架）
     *   - 路径不在当前 manifest.uniapp.pages 内（manifest 改了页面）
     *   - allowHome=false（manifest 关闭了首页能力）
     *   - allowTabBar=false（manifest 关闭了 tabBar 能力）
     *
     * DB 保持不变（保留租户原始意图，恢复后自动生效）。
     *
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    private function filterByEntitlements(int $tenantId, array $cfg): array
    {
        $homeCode = (string) ($cfg['home_app_code'] ?? '');
        $homePage = (string) ($cfg['home_page'] ?? '');

        if ($homeCode !== '') {
            if (isset(self::BUILTIN_PAGES[$homeCode])) {
                // 内置首页：path 必须匹配（或为空），免 entitlement
                $invalid = $homePage !== '' && $homePage !== self::BUILTIN_PAGES[$homeCode];
            } else {
                $invalid = !$this->entitlementService->has($tenantId, $homeCode)
                    || !$this->pluginAllowsHome($homeCode)
                    || ($homePage !== '' && !$this->isPathInPlugin($homeCode, $homePage));
            }
            if ($invalid) {
                $cfg['home_app_code'] = '';
                $cfg['home_page']     = '';
            }
        }

        $tabbar = is_array($cfg['tabbar'] ?? null) ? $cfg['tabbar'] : [];
        $cfg['tabbar'] = array_values(array_filter(
            $tabbar,
            function ($item) use ($tenantId) {
                if (!is_array($item) || !isset($item['code'], $item['path'])) {
                    return false;
                }
                $code = (string) $item['code'];
                $path = ltrim((string) $item['path'], '/');
                if (isset(self::BUILTIN_PAGES[$code])) {
                    return explode('?', $path, 2)[0] === self::BUILTIN_PAGES[$code];
                }
                if ($code === self::DIY_CODE) {
                    return str_starts_with(explode('?', $path, 2)[0], 'pages/diy/index');
                }
                $basePath = explode('?', $path, 2)[0];
                return $this->entitlementService->has($tenantId, $code)
                    && $this->pluginAllowsTabBar($code)
                    && $this->isPathInPlugin($code, $basePath);
            },
        ));
        return $cfg;
    }

    private function isPathInPlugin(string $entitlementCode, string $path): bool
    {
        return in_array($path, $this->buildPagePathWhitelist($entitlementCode), true);
    }

    /**
     * 保存配置。null 字段不更新。
     *
     * @param array<string, mixed> $input
     */
    public function save(int $tenantId, array $input): array
    {
        $patch = [];

        if (isset($input['app_name'])) {
            $patch['app_name']    = (string) $input['app_name'];
        }
        if (isset($input['app_logo'])) {
            $patch['app_logo']    = (string) $input['app_logo'];
        }
        if (isset($input['wechat_appid'])) {
            $patch['wechat_appid'] = (string) $input['wechat_appid'];
        }
        if (array_key_exists('wechat_upload_version', $input)) {
            $ver = trim((string) $input['wechat_upload_version']);
            if ($ver === '' || !preg_match('/^\d+\.\d+\.\d+$/', $ver)) {
                throw new BusinessException('wechat_upload_version 必须为语义化版本号（如 1.0.3）', 422);
            }
            $patch['wechat_upload_version'] = $ver;
        }
        if (array_key_exists('wechat_upload_desc', $input)) {
            $desc = trim((string) $input['wechat_upload_desc']);
            if (mb_strlen($desc) > 200) {
                throw new BusinessException('wechat_upload_desc 最长 200 字', 422);
            }
            $patch['wechat_upload_desc'] = $desc === '' ? '租户后台发布' : $desc;
        }

        if (array_key_exists('theme_color', $input)) {
            $color = (string) $input['theme_color'];
            if ($color !== '' && !preg_match(self::HEX_COLOR_REGEX, $color)) {
                throw new BusinessException("theme_color 必须为 6 位十六进制色值（如 #2979ff）：{$color}", 422);
            }
            $patch['theme_color'] = $color;
        }

        if (array_key_exists('app_intro', $input)) {
            $patch['app_intro'] = (string) $input['app_intro'];
        }
        if (array_key_exists('service_phone', $input)) {
            $patch['service_phone'] = (string) $input['service_phone'];
        }
        if (array_key_exists('share_title', $input)) {
            $patch['share_title'] = (string) $input['share_title'];
        }
        if (array_key_exists('share_image', $input)) {
            $patch['share_image'] = (string) $input['share_image'];
        }
        if (array_key_exists('service_type', $input)) {
            $st = (string) $input['service_type'];
            if (!in_array($st, ['', 'online', 'wechat', 'phone'], true)) {
                throw new BusinessException("service_type 非法：{$st}", 422);
            }
            $patch['service_type'] = $st;
        }
        if (array_key_exists('theme_colors', $input)) {
            $colors = is_array($input['theme_colors']) ? $input['theme_colors'] : [];
            $this->assertColors($colors, 'theme_colors');
            $patch['theme_colors'] = json_encode((object) $colors, JSON_UNESCAPED_UNICODE);
            // 与旧单色列双写
            if (isset($colors['primary'])) {
                $patch['theme_color'] = (string) $colors['primary'];
            }
        }
        if (array_key_exists('tabbar_style', $input)) {
            $ts = is_array($input['tabbar_style']) ? $input['tabbar_style'] : [];
            $this->assertColors($ts, 'tabbar_style');
            $patch['tabbar_style'] = json_encode((object) $ts, JSON_UNESCAPED_UNICODE);
        }

        $homeCode = array_key_exists('home_app_code', $input) ? (string) $input['home_app_code'] : null;
        $homePage = array_key_exists('home_page', $input) ? (string) $input['home_page'] : null;

        // v2.6.4 issue #2：部分更新防御 —— 改 home_app_code 但不传 home_page，
        // 旧 home_page 会留在 DB 与新 home_app_code 不匹配。强制要求一起提交。
        if ($homeCode !== null && $homePage === null) {
            $existing = $this->repository->findByTenantId($tenantId);
            $existingCode = (string) ($existing['home_app_code'] ?? '');
            if ($homeCode !== $existingCode) {
                throw new BusinessException(
                    '修改 home_app_code 必须同时提交 home_page（防止旧路径残留）',
                    422,
                );
            }
        }

        if ($homeCode !== null) {
            if ($homeCode !== '' && !isset(self::BUILTIN_PAGES[$homeCode])) {
                if (!$this->entitlementService->has($tenantId, $homeCode)) {
                    throw new BusinessException("租户未授权该首页应用：{$homeCode}", 403);
                }
                // v2.6.2 issue #1：后端强校验 allowHome；MobileEligibilityService 只
                // 是给前端下拉用的，不能依赖前端隐藏来防止直接调 API 写入。
                if (!$this->pluginAllowsHome($homeCode)) {
                    throw new BusinessException(
                        "插件 {$homeCode} 不允许作为移动端首页（manifest.uniapp.allowHome=false）",
                        422,
                    );
                }
            }
            $patch['home_app_code'] = $homeCode;
        }
        if ($homePage !== null) {
            // home_page 必须在 home_app_code 对应插件的 manifest.uniapp.pages 白名单内，
            // 否则租户可能保存未编译页面 / 错误页面 / code 与 path 不匹配的配置。
            if ($homePage !== '') {
                $codeForCheck = $homeCode ?? (string) ($this->repository->findByTenantId($tenantId)['home_app_code'] ?? '');
                if ($codeForCheck === '') {
                    throw new BusinessException('设置 home_page 必须同时设置 home_app_code', 422);
                }
                if (isset(self::BUILTIN_PAGES[$codeForCheck])) {
                    if ($homePage !== self::BUILTIN_PAGES[$codeForCheck]) {
                        throw new BusinessException("home_page '{$homePage}' 与内置首页 {$codeForCheck} 不匹配", 422);
                    }
                } else {
                    $this->assertPathBelongsToPlugin($codeForCheck, $homePage, 'home_page');
                }
            }
            $patch['home_page'] = $homePage;
        }

        if (array_key_exists('tabbar', $input)) {
            $items = $this->validateTabbar($tenantId, (array) $input['tabbar']);
            $patch['tabbar_json'] = $items;
        }

        $this->repository->upsert($tenantId, $patch);
        // v2.6.4 issue #2：返回经 filterByEntitlements 处理过的视图，与 get() 一致；
        // 避免 Controller 拿到 raw row（含敏感字段、未做权益/路径软隐藏）。
        return $this->get($tenantId);
    }

    /**
     * 给定插件 entitlement code，构造该插件允许的完整页面路径白名单：
     *   {manifest.uniapp.subpackage}/{manifest.uniapp.pages[].path}
     *
     * @return array<int, string>
     */
    private function buildPagePathWhitelist(string $entitlementCode): array
    {
        $plugin = $this->pluginRepository->findByEntitlement($entitlementCode)
            ?? $this->pluginRepository->findByCode($entitlementCode);
        if (!$plugin) {
            return [];
        }
        $manifest = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);
        $uniapp     = is_array($manifest['uniapp'] ?? null) ? $manifest['uniapp'] : [];
        $subpackage = (string) ($uniapp['subpackage'] ?? '');
        $pages      = is_array($uniapp['pages'] ?? null) ? $uniapp['pages'] : [];
        if ($subpackage === '' || $pages === []) {
            return [];
        }
        $sub = rtrim($subpackage, '/');
        $out = [];
        foreach ($pages as $p) {
            $path = (string) ($p['path'] ?? '');
            if ($path !== '') {
                $out[] = $sub . '/' . ltrim($path, '/');
            }
        }
        return $out;
    }

    private function assertPathBelongsToPlugin(string $entitlementCode, string $path, string $field): void
    {
        $whitelist = $this->buildPagePathWhitelist($entitlementCode);
        if (!in_array($path, $whitelist, true)) {
            $hint = $whitelist === []
                ? '该插件 manifest 未声明 uniapp.pages'
                : '允许的值：' . implode(' / ', $whitelist);
            throw new BusinessException(
                "{$field} '{$path}' 不属于插件 {$entitlementCode} 的页面；{$hint}",
                422,
            );
        }
    }

    /**
     * 校验 tabBar 数组，并返回规范化形式。
     * 以 path 为主：服务端按 path 反推 code（兼容旧客户端仍传 code）。
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function validateTabbar(int $tenantId, array $items): array
    {
        $normalized = [];
        foreach (array_values($items) as $i => $item) {
            if (!is_array($item)) {
                throw new BusinessException("tabbar[{$i}] 必须是对象", 422);
            }
            $path = $this->normalizeTabbarPath((string) ($item['path'] ?? ''), "tabbar[{$i}].path");
            $text = (string) ($item['text'] ?? '');
            if ($path === '' || $text === '') {
                throw new BusinessException("tabbar[{$i}] 缺少 path/text", 422);
            }
            $code = $this->resolveTabbarCodeFromPath($tenantId, $path, "tabbar[{$i}].path");
            $normalized[] = [
                'code'          => $code,
                'path'          => $path,
                'text'          => $text,
                'icon'          => (string) ($item['icon'] ?? ''),
                'selected_icon' => (string) ($item['selected_icon'] ?? ''),
                'sel_label'     => (string) ($item['sel_label'] ?? ''),
                'badge'         => (string) ($item['badge'] ?? ''),
            ];
        }
        return $normalized;
    }

    /** 站内 path 规范化：禁外链、去前导 / */
    private function normalizeTabbarPath(string $path, string $field): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path) === 1) {
            throw new BusinessException("{$field} 不能使用外链，请选择站内页面", 422);
        }
        return ltrim($path, '/');
    }

    /**
     * 按 path 反推 tabBar code：内置 → DIY 自定义页 → 已授权插件页面白名单。
     */
    private function resolveTabbarCodeFromPath(int $tenantId, string $path, string $field): string
    {
        $base = explode('?', $path, 2)[0];
        foreach (self::BUILTIN_PAGES as $code => $builtinPath) {
            if ($base === $builtinPath) {
                return $code;
            }
        }

        if ($base === 'pages/diy/index') {
            $query = [];
            $qStr  = explode('?', $path, 2)[1] ?? '';
            if ($qStr !== '') {
                parse_str($qStr, $query);
            }
            $key = (string) ($query['key'] ?? '');
            if ($key !== '') {
                $page = $this->diyPageService->getPublishedForTenant($tenantId, $key);
                if ($page === null) {
                    throw new BusinessException("{$field} DIY 页面「{$key}」不存在或未发布", 422);
                }
            }
            return self::DIY_CODE;
        }

        // 扫描权益内可作 tabBar 的插件页面白名单
        foreach ($this->entitlementService->list($tenantId) as $ent) {
            $code = $ent->code;
            if (!$this->pluginAllowsTabBar($code)) {
                continue;
            }
            if ($this->isPathInPlugin($code, $base)) {
                return $code;
            }
        }

        throw new BusinessException(
            "{$field} '{$path}' 不是可作底部导航的站内页（需为内置页、已发布 DIY 页或已授权插件页面）",
            422,
        );
    }

    /**
     * 判断某插件是否允许作为 tabBar 项：
     *   - 找不到 plugin 行 → false（防御）
     *   - manifest.uniapp.allowTabBar 显式声明 → 用其值
     *   - 否则按 kind 默认：app=true，plugin=false
     */
    private function pluginAllowsTabBar(string $entitlementCode): bool
    {
        return $this->pluginAllows($entitlementCode, 'allowTabBar');
    }

    /**
     * 判断某插件是否允许作为移动端首页（与 pluginAllowsTabBar 对称）。
     */
    private function pluginAllowsHome(string $entitlementCode): bool
    {
        return $this->pluginAllows($entitlementCode, 'allowHome');
    }

    /**
     * @param string $flag 'allowHome' | 'allowTabBar'
     */
    private function pluginAllows(string $entitlementCode, string $flag): bool
    {
        $plugin = $this->pluginRepository->findByEntitlement($entitlementCode)
            ?? $this->pluginRepository->findByCode($entitlementCode);
        if (!$plugin) {
            return false;
        }

        $manifest = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);
        $kind   = (string) ($plugin['kind'] ?? 'plugin');
        $uniapp = is_array($manifest['uniapp'] ?? null) ? $manifest['uniapp'] : [];

        if (array_key_exists($flag, $uniapp)) {
            return (bool) $uniapp[$flag];
        }
        return $kind === 'app';
    }

    private static function normalizeUploadVersion(string $version): string
    {
        $version = trim($version);
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1 ? $version : '1.0.0';
    }

    private static function normalizeUploadDesc(string $desc): string
    {
        $desc = trim($desc);
        return $desc === '' ? '租户后台发布' : mb_substr($desc, 0, 200);
    }
}
