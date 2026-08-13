<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use app\repository\plugin\PluginRepository;
use app\service\saas\EntitlementService;

/**
 * 生成租户专属 pages.json：
 *   1. 读 uniapp/src/pages.base.json 作为主壳
 *   2. 遍历租户权益清单，匹配 plugin 行，读 manifest.uniapp.pages + subpackage
 *   3. 按权益过滤后合并到 subPackages 字段
 *
 * 与 scripts/merge-pages-json.mjs 的区别：
 *   - 后者全量合并（dev 时所有插件都打包进去）
 *   - 本类按 EntitlementService 筛选，仅生成租户实际可用的页面
 *
 * 返回值：merged pages.json + 实际合入的插件 code 列表（写入 included_plugins_json 快照）。
 */
final class PagesJsonGenerator
{
    public function __construct(
        private readonly EntitlementService $entitlementService,
        private readonly PluginRepository $pluginRepository,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $tenantTabbar  来自 tenant_mobile_configs.tabbar_json，
     *                                                       每项含 code/path/text/icon/selected_icon。
     *                                                       空数组时保留 pages.base.json 的默认 tabBar.list
     * @return array{pagesJson: array<string, mixed>, includedPlugins: array<int, string>, baseDir: string}
     */
    public function generate(int $tenantId, string $templateUniappDir, array $tenantTabbar = []): array
    {
        $basePath = $templateUniappDir . '/src/pages.base.json';
        if (!is_file($basePath)) {
            throw new \RuntimeException("pages.base.json not found at {$basePath}");
        }
        $base = json_decode((string) file_get_contents($basePath), true);
        if (!is_array($base)) {
            throw new \RuntimeException('pages.base.json is not valid JSON');
        }

        $base['subPackages'] = is_array($base['subPackages'] ?? null) ? $base['subPackages'] : [];
        $existingRoots = [];
        foreach ($base['subPackages'] as $sp) {
            if (is_array($sp) && isset($sp['root'])) {
                $existingRoots[(string) $sp['root']] = true;
            }
        }

        $included = [];

        foreach ($this->entitlementService->list($tenantId) as $ent) {
            $plugin = $this->findPlugin($ent->code);
            if (!$plugin) {
                continue;
            }

            $manifest = is_array($plugin['manifest'] ?? null)
                ? $plugin['manifest']
                : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);
            $uniapp = is_array($manifest['uniapp'] ?? null) ? $manifest['uniapp'] : [];
            $root   = (string) ($uniapp['subpackage'] ?? '');
            $pages  = is_array($uniapp['pages'] ?? null) ? $uniapp['pages'] : [];

            if ($root === '' || empty($pages)) {
                continue;
            }
            if (isset($existingRoots[$root])) {
                throw new \RuntimeException("subpackage root '{$root}' 与主壳已存在的 subpackage 冲突");
            }

            $base['subPackages'][] = [
                'root'  => $root,
                'pages' => array_map(
                    static fn (array $p) => [
                        'path'  => (string) ($p['path']  ?? ''),
                        'style' => ['navigationBarTitleText' => (string) ($p['title'] ?? '')],
                    ],
                    $pages,
                ),
            ];
            $existingRoots[$root] = true;
            $included[] = (string) ($plugin['code'] ?? $ent->code);
        }

        // 应用租户配置的 tabBar：仅在 tenantTabbar 非空时改写 base.tabBar.list；
        // 空列表保留 pages.base.json 默认 tabBar（避免开发态被空配置破坏）。
        $appliedTenantTabbar = false;
        if ($tenantTabbar !== []) {
            $base = $this->applyTenantTabbar($base, $tenantTabbar);
            $appliedTenantTabbar = true;
        }

        // #region agent log
        $__list = is_array($base['tabBar']['list'] ?? null) ? $base['tabBar']['list'] : [];
        $__log = [
            'sessionId' => 'ddd612',
            'hypothesisId' => 'C',
            'location' => 'PagesJsonGenerator.php:generate',
            'message' => 'pages.json tabBar result',
            'data' => [
                'tenantId' => $tenantId,
                'inputTabbarCount' => count($tenantTabbar),
                'appliedTenantTabbar' => $appliedTenantTabbar,
                'outputListCount' => count($__list),
                'outputTexts' => array_values(array_map(static fn ($r) => (string) ($r['text'] ?? ''), $__list)),
                'custom' => (bool) ($base['tabBar']['custom'] ?? false),
            ],
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        $__logPath = dirname(__DIR__, 3) . '/.cursor/debug-ddd612.log';
        if (!is_dir(dirname($__logPath))) {
            @mkdir(dirname($__logPath), 0775, true);
        }
        @file_put_contents($__logPath, json_encode($__log, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        // #endregion

        return [
            'pagesJson'       => $base,
            'includedPlugins' => $included,
            'baseDir'         => $templateUniappDir,
        ];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function applyTenantTabbar(array $base, array $items): array
    {
        $existingTabbar = is_array($base['tabBar'] ?? null) ? $base['tabBar'] : [];
        $existingList   = is_array($existingTabbar['list'] ?? null) ? $existingTabbar['list'] : [];

        // base tabBar 按 pagePath 建图标索引：内置页面（首页/我的等）未配图标时按路径回退，
        // 比按下标回退更稳健（租户重排不会拿错图标）。
        $baseIconByPath = [];
        foreach ($existingList as $row) {
            if (is_array($row) && isset($row['pagePath'])) {
                $baseIconByPath[(string) $row['pagePath']] = $row;
            }
        }

        $list = [];
        foreach (array_values($items) as $i => $item) {
            $path = ltrim((string) ($item['path'] ?? ''), '/');
            $text = (string) ($item['text'] ?? '');
            if ($path === '' || $text === '') {
                continue;
            }
            // 原生 tabBar.list.pagePath 不支持 query；DIY 等带参路径只保留页面本体
            $pagePath = explode('?', $path, 2)[0];
            $fallback = $baseIconByPath[$pagePath] ?? ($existingList[$i] ?? []);
            $list[] = [
                'pagePath'         => $pagePath,
                'text'             => $text,
                'iconPath'         => (string) ($item['icon']          ?: ($fallback['iconPath']         ?? '')),
                'selectedIconPath' => (string) ($item['selected_icon'] ?: ($fallback['selectedIconPath'] ?? '')),
            ];
        }
        if ($list === []) {
            return $base;
        }

        $base['tabBar'] = array_merge($existingTabbar, ['list' => $list]);
        return $base;
    }

    private function findPlugin(string $entitlement): ?array
    {
        return $this->pluginRepository->findByEntitlement($entitlement)
            ?? $this->pluginRepository->findByCode($entitlement);
    }
}
