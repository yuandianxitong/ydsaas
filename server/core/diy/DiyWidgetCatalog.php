<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\diy;

use app\service\saas\EntitlementService;
use core\diy\contracts\DiyWidgetHydrator;
use core\plugin\PluginRegistry;

/**
 * 装修 widget 目录：解析「内置 11 + 该租户授权插件声明的类型」与对应 hydrator。
 * 按租户 entitlement 门控；类型与内置/跨插件冲突时跳过 + error_log。
 */
class DiyWidgetCatalog
{
    /** @var array<int, array<int,array{type:string,hydrator:string,plugin:string,label:string,render:string,default_props:array,props_schema:array,pages:?array,renderer:?array,icon_url:string,text_keys:array,raw_keys:array}>> 请求内按租户缓存，避免热点路径重复遍历 registry */
    private array $pluginWidgetsCache = [];

    public function __construct(
        private readonly PluginRegistry      $registry,
        private readonly EntitlementService  $entitlement,
    ) {
    }

    /**
     * 该租户可用的 widget 类型集（内置 + 授权插件声明）。
     * @return array<int,string>
     */
    public function typesForTenant(int $tenantId): array
    {
        $types = DiyWidgetRegistry::TYPES;
        foreach ($this->pluginWidgets($tenantId) as $w) {
            $types[] = $w['type'];
        }
        return array_values(array_unique($types));
    }

    /**
     * 解析某 type 的 hydrator：授权插件 + 声明了 hydrator + 可实例化且 instanceof 契约。
     * 内置/装饰/未授权/不可解析 → null（调用方据此原样保留或丢弃）。
     */
    public function hydratorFor(string $type, int $tenantId): ?DiyWidgetHydrator
    {
        foreach ($this->pluginWidgets($tenantId) as $w) {
            if ($w['type'] !== $type) {
                continue;
            }
            $class = $w['hydrator'];
            if ($class === '') {
                return null;
            }
            try {
                $obj = app()->make($class);
            } catch (\Throwable) {
                return null;
            }
            return $obj instanceof DiyWidgetHydrator ? $obj : null;
        }
        return null;
    }

    /**
     * 该租户授权插件 widget 的编辑器元数据（供 catalog 端点 / 编辑器）。
     * plugin + renderer 供编辑器宿主按「插件 code + 渲染器组件名」解析插件自带渲染器（协议 v1）；
     * icon_url 为面板图标（widget 声明 → 插件 logo → '' 三级回退，'' 时前端用占位图标）。
     * @return array<int,array{type:string,label:string,render:string,plugin:string,renderer:?array,icon_url:string,default_props:array,props_schema:array,pages:?array}>
     */
    public function pluginWidgetMetaForTenant(int $tenantId): array
    {
        $out = [];
        foreach ($this->pluginWidgets($tenantId) as $w) {
            $out[] = [
                'type'          => $w['type'],
                'label'         => $w['label'],
                'render'        => $w['render'],
                'plugin'        => $w['plugin'],
                'renderer'      => $w['renderer'],
                'icon_url'      => $w['icon_url'],
                'default_props' => $w['default_props'],
                'props_schema'  => $w['props_schema'],
                'pages'         => isset($w['pages']) && is_array($w['pages']) ? array_values(array_map('strval', $w['pages'])) : null,
            ];
        }
        return $out;
    }

    /**
     * 供 NormalizedWidget::normalize() 的信任源元数据：manifest 声明的 render + item_fields 追加白名单。
     * 未知 type（调用方已先过 typesForTenant 校验，理论不发生）→ 保守缺省（render=list、无追加字段）。
     * @return array{render:string,text_keys:array<int,string>,raw_keys:array<int,string>}
     */
    public function widgetMetaForNormalize(string $type, int $tenantId): array
    {
        foreach ($this->pluginWidgets($tenantId) as $w) {
            if ($w['type'] === $type) {
                return ['render' => $w['render'], 'text_keys' => $w['text_keys'], 'raw_keys' => $w['raw_keys']];
            }
        }
        return ['render' => 'list', 'text_keys' => [], 'raw_keys' => []];
    }

    /**
     * 该租户授权插件声明的 widget（已去重内置/跨插件冲突）。
     * @return array<int,array{type:string,hydrator:string,plugin:string,label:string,render:string,renderer:?array,icon_url:string,text_keys:array<int,string>,raw_keys:array<int,string>,default_props:array,props_schema:array,pages:?array}>
     */
    private function pluginWidgets(int $tenantId): array
    {
        if (isset($this->pluginWidgetsCache[$tenantId])) {
            return $this->pluginWidgetsCache[$tenantId];
        }
        $seen = array_fill_keys(DiyWidgetRegistry::TYPES, true);
        $out = [];
        foreach ($this->registry->all() as $code => $meta) {
            $manifest = (array) ($meta['manifest'] ?? []);
            $ent = (string) ($manifest['entitlement'] ?? $code);
            if (!$this->entitlement->has($tenantId, $ent)) {
                continue;
            }
            foreach ((array) ($manifest['diy_widgets'] ?? []) as $w) {
                if (!is_array($w)) {
                    continue;
                }
                $type = (string) ($w['type'] ?? '');
                if ($type === '') {
                    continue;
                }
                if (isset($seen[$type])) {
                    error_log("[diy] widget type collision skipped: {$type} (plugin {$code})");
                    continue;
                }
                $seen[$type] = true;
                // 协议 v1：renderer（三端渲染器组件名，供前端注册表/宿主）+ item_fields（item 字段追加白名单）
                $renderer = isset($w['renderer']) && is_array($w['renderer']) ? $w['renderer'] : null;
                // widget 图标三级回退：widget 声明（assets/diy/ 通道）→ 插件 logo → ''（前端占位图标）
                $iconUrl = \core\plugin\PluginIconResolver::diyWidgetIconUrl($code, (string) ($w['icon'] ?? ''));
                if ($iconUrl === '') {
                    $iconUrl = \core\plugin\PluginIconResolver::iconUrl($code, (string) ($manifest['icon'] ?? ''));
                }
                $textKeys = [];
                $rawKeys = [];
                foreach ((array) ($w['item_fields'] ?? []) as $f) {
                    if (!is_array($f) || ($f['key'] ?? '') === '') {
                        continue;
                    }
                    if (($f['type'] ?? 'text') === 'raw') {
                        $rawKeys[] = (string) $f['key'];
                    } else {
                        $textKeys[] = (string) $f['key'];
                    }
                }
                $out[] = [
                    'type'          => $type,
                    'hydrator'      => (string) ($w['hydrator'] ?? ''),
                    'plugin'        => (string) $code,
                    'label'         => (string) ($w['label'] ?? $type),
                    'render'        => (string) ($w['render'] ?? 'list'),
                    'renderer'      => $renderer,
                    'icon_url'      => $iconUrl,
                    'text_keys'     => $textKeys,
                    'raw_keys'      => $rawKeys,
                    'default_props' => (array) ($w['default_props'] ?? []),
                    'props_schema'  => (array) ($w['props_schema'] ?? []),
                    'pages'         => isset($w['pages']) && is_array($w['pages']) ? array_values(array_map('strval', $w['pages'])) : null,
                ];
            }
        }
        return $this->pluginWidgetsCache[$tenantId] = $out;
    }
}
