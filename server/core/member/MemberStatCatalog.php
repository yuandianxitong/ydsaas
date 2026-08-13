<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\member;

use app\service\saas\EntitlementService;
use core\plugin\contracts\MemberStatProvider;
use core\plugin\PluginRegistry;

/**
 * 会员统计键目录：内置键（user.*）+ 该租户授权插件在 manifest `member_stats` 声明的键。
 * 编辑器目录下发与 C 端聚合分发共用同一份计算（entitlement 门控）。
 * 结构对齐 DiyWidgetCatalog：registry 遍历 + 请求内按租户缓存。
 */
class MemberStatCatalog
{
    public const BUILTIN = [
        ['key' => 'user.balance', 'label' => '余额', 'plugin' => '', 'raw_key' => 'balance'],
        ['key' => 'user.points',  'label' => '积分', 'plugin' => '', 'raw_key' => 'points'],
    ];

    /** @var array<int, array<int,array{key:string,label:string,plugin:string,raw_key:string,provider:string}>> */
    private array $cache = [];

    public function __construct(
        private readonly PluginRegistry     $registry,
        private readonly EntitlementService $entitlement,
    ) {
    }

    /** @return array<int,array{key:string,label:string,plugin:string}> 编辑器/前端目录（全限定 key） */
    public function forTenant(int $tenantId): array
    {
        $out = [];
        foreach (array_merge(self::BUILTIN, $this->pluginEntries($tenantId)) as $e) {
            $out[] = ['key' => $e['key'], 'label' => $e['label'], 'plugin' => $e['plugin']];
        }
        return $out;
    }

    /** @return array<string,array{plugin:string,raw_key:string}> 全限定 key → 归属（含内置 plugin=''） */
    public function keyIndex(int $tenantId): array
    {
        $idx = [];
        foreach (array_merge(self::BUILTIN, $this->pluginEntries($tenantId)) as $e) {
            $idx[$e['key']] = ['plugin' => $e['plugin'], 'raw_key' => $e['raw_key']];
        }
        return $idx;
    }

    /** 解析插件 provider：授权 + 声明 + 可实例化 + instanceof 契约，失败一律 null。 */
    public function providerFor(string $pluginCode, int $tenantId): ?MemberStatProvider
    {
        foreach ($this->pluginEntries($tenantId) as $e) {
            if ($e['plugin'] !== $pluginCode) {
                continue;
            }
            try {
                $obj = app()->make($e['provider']);
            } catch (\Throwable) {
                return null;
            }
            return $obj instanceof MemberStatProvider ? $obj : null;
        }
        return null;
    }

    /** @return array<int,array{key:string,label:string,plugin:string,raw_key:string,provider:string}> */
    private function pluginEntries(int $tenantId): array
    {
        if (isset($this->cache[$tenantId])) {
            return $this->cache[$tenantId];
        }
        $out = [];
        foreach ($this->registry->all() as $code => $meta) {
            $manifest = (array) ($meta['manifest'] ?? []);
            $ent = (string) ($manifest['entitlement'] ?? $code);
            if (!$this->entitlement->has($tenantId, $ent)) {
                continue;
            }
            $ms = $manifest['member_stats'] ?? null;
            if (!is_array($ms) || ($ms['provider'] ?? '') === '') {
                continue;
            }
            foreach ((array) ($ms['keys'] ?? []) as $k) {
                if (!is_array($k) || ($k['key'] ?? '') === '') {
                    continue;
                }
                $out[] = [
                    'key'      => "{$code}." . (string) $k['key'],
                    'label'    => (string) ($k['label'] ?? $k['key']),
                    'plugin'   => (string) $code,
                    'raw_key'  => (string) $k['key'],
                    'provider' => (string) $ms['provider'],
                ];
            }
        }
        return $this->cache[$tenantId] = $out;
    }
}
