<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\diy;

use app\service\saas\EntitlementService;
use core\plugin\PluginRegistry;

/**
 * 装修链接目录（插件部分）：解析「该租户授权插件的 uniapp.pages 自动链接 + diy_links 显式链接」。
 * 与 DiyWidgetCatalog 同款 entitlement 门控；同一 path 时 diy_links 覆盖自动派生项。
 *
 * 流程中间态页面（如支付成功/发布成功回跳页）可在 uniapp.pages 项上声明
 * `"no_diy_link": true`，不参与装修链接自动派生；页面注册（PagesJsonGenerator）
 * 只读 path/title，不受该声明影响。
 */
class DiyLinkCatalog
{
    /** @var array<int,array<int,array>> 请求内按租户缓存 */
    private array $cache = [];

    public function __construct(
        private readonly PluginRegistry     $registry,
        private readonly EntitlementService $entitlement,
    ) {
    }

    /**
     * @return array<int,array{label:string,path:string,category:string,source:string,params_schema:array,external:bool}>
     */
    public function pluginLinksForTenant(int $tenantId): array
    {
        if (isset($this->cache[$tenantId])) {
            return $this->cache[$tenantId];
        }
        $byPath = [];   // path => item（diy_links 优先）
        foreach ($this->registry->all() as $code => $meta) {
            $manifest = (array) ($meta['manifest'] ?? []);
            $ent = (string) ($manifest['entitlement'] ?? $code);
            if (!$this->entitlement->has($tenantId, $ent)) {
                continue;
            }
            $name = (string) ($manifest['name'] ?? $code);

            // 1) uniapp.pages 自动派生（无参基础链接）
            $uniapp = (array) ($manifest['uniapp'] ?? []);
            $sub = trim((string) ($uniapp['subpackage'] ?? ''), '/');
            foreach ((array) ($uniapp['pages'] ?? []) as $pg) {
                $p = (string) ($pg['path'] ?? '');
                if ($p === '' || $sub === '') {
                    continue;
                }
                // 流程中间态页（no_diy_link:true）不参与自动派生（见类 docblock）
                if (!empty($pg['no_diy_link'])) {
                    continue;
                }
                $path = '/' . $sub . '/' . ltrim($p, '/');
                $byPath[$path] = [
                    'label' => (string) ($pg['title'] ?? $path),
                    'path' => $path, 'category' => $name,
                    'source' => 'plugin', 'params_schema' => [], 'external' => false,
                ];
            }

            // 2) diy_links 显式（覆盖同 path 的自动项；同时移除与显式 path 同基路径的自动派生项）
            foreach ((array) ($manifest['diy_links'] ?? []) as $l) {
                if (!is_array($l)) {
                    continue;
                }
                $path = (string) ($l['path'] ?? '');
                $label = (string) ($l['label'] ?? '');
                if ($path === '' || $label === '') {
                    continue;
                }
                // 移除与此显式链接同基路径（? 前部分）的自动派生项，避免重复
                $basePath = explode('?', $path, 2)[0];
                if ($basePath !== $path) {
                    unset($byPath[$basePath]);
                }
                $byPath[$path] = [
                    'label' => $label, 'path' => $path,
                    'category' => (string) ($l['category'] ?? $name),
                    'source' => 'plugin-link',
                    'params_schema' => (array) ($l['params_schema'] ?? []),
                    'external' => false,
                ];
            }
        }
        return $this->cache[$tenantId] = array_values($byPath);
    }
}
