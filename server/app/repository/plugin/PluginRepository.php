<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\Plugin;
use core\base\Repository;
use core\plugin\PluginScanner;
use core\runtime\RuntimePaths;
use think\Model;

class PluginRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new Plugin();
    }

    /**
     * 获取所有已启用的插件（按 boot 顺序）
     *
     * @return array<int, array<string, mixed>>
     */
    public function listEnabled(): array
    {
        return $this->getModel()->db()
            ->where('status', Plugin::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->order('id asc')
            ->select()
            ->toArray();
    }

    /**
     * 按 code 查找（不含软删）
     */
    public function findByCode(string $code): ?array
    {
        $row = $this->getModel()->db()
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->find();
        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * 列出所有 marketplace 来源（distribution_source='marketplace'）的插件 code。
     *
     * 按 code 长度倒序排，让 longer prefix 先匹配 - 避免 `/api/crm/` 被
     * `/api/cr/` 误命中。专供 RuntimeLicenseGuard 做 URL 前缀匹配。
     *
     * 缓存 60s：RuntimeLicenseGuard::handle() 是 Task 19 之后挂到三应用的热路径，
     * 每请求 SELECT 会带来不必要的 DB 压力。新装/卸载 plugin 最坏 60s 后被 guard
     * 感知，可接受。测试需在 setUp/tearDown 清 cache key 避免污染。
     *
     * @return string[]
     */
    public function listMarketplaceCodes(): array
    {
        return \think\facade\Cache::remember('license_guard:marketplace_codes', function () {
            $codes = $this->getModel()->db()
                ->where('distribution_source', 'marketplace')
                ->whereNull('deleted_at')
                ->column('code');
            $codes = array_map('strval', $codes);
            usort($codes, fn ($a, $b) => strlen($b) - strlen($a));
            return $codes;
        }, 60);
    }

    /**
     * 按 entitlement 列查找（不含软删）。
     *
     * entitlement 与 code 可不同（manifest 显式指定）；调用方先按 entitlement
     * 查，没命中再退回 findByCode。
     */
    public function findByEntitlement(string $entitlement): ?array
    {
        $row = $this->getModel()->db()
            ->where('entitlement', $entitlement)
            ->whereNull('deleted_at')
            ->find();
        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * 按 id 查找（含软删行）。
     *
     * v2.6.4：sync.diff 已能看到「先 grant 后被软删」的旧 grant 行，但 removed
     * 分支用 find($pid) 走 SoftDelete trait 时拿不到软删插件，导致 kind=app 的
     * 残留菜单无法被识别拆除。该方法走 Db facade 绕过 SoftDelete scope。
     */
    public function findWithTrashedById(int $id): ?array
    {
        $row = \think\facade\Db::table('plugins')->where('id', $id)->find();
        return $row ?: null;
    }

    /**
     * 按 code 查找（含软删行）— 给 upload 复活流程用。
     *
     * 故意走 Db facade 而不是 Model：
     *   - core\base\Model 用 SoftDelete trait，查询/update 默认都过滤 deleted_at IS NULL
     *   - Model::$hidden 里有 'deleted_at'，toArray() 会把这字段丢掉，
     *     调用方拿不到软删标志没法判断
     * 直接 Db::table 既绕过软删 scope，又能拿到完整原始字段。
     */
    public function findByCodeWithTrashed(string $code): ?array
    {
        $row = \think\facade\Db::table('plugins')
            ->where('code', $code)
            ->find();
        return $row ?: null;
    }

    /**
     * 复活一条软删的 plugins 行（给 upload 走 update 路径用）。
     * 用 raw db 强制写入 deleted_at=NULL，绕开 SoftDelete trait + tenant scope。
     */
    public function reviveAndUpdate(int $id, array $data): bool
    {
        return \think\facade\Db::table('plugins')
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * 带 kind 过滤的列表查询。
     *
     * 合并两路：
     *   1. DB 行（plugins 表，已上传 / 已安装 / 失败等）
     *   2. 磁盘上 server/plugins/{code}/plugin.json 存在但 DB 没登记的——
     *      生成虚拟行（id=0, status=0 "未安装", source=SOURCE_BUILTIN）让管理端
     *      可见，前端按 id===0 路由到 install-from-disk 接口
     *
     * 排序：DB 行优先（id desc），虚拟行接在末尾（按 code asc）。
     *
     * @return array{list:array<int,array<string,mixed>>, pagination:array{total:int,page:int,limit:int}}
     */
    /**
     * v2.7.4：把行的 icon 字段（manifest 原值，如 "icon.png"）转成浏览器可拉的 URL。
     */
    private function withIconUrl(array $row): array
    {
        $row['icon'] = \core\plugin\PluginIconResolver::iconUrl(
            (string) ($row['code'] ?? ''),
            (string) ($row['icon'] ?? '')
        );
        return $row;
    }

    public function listWithKind(?string $kind, int $page, int $limit): array
    {
        // DB 全量（不分页，因为还要合并虚拟行）
        $q = $this->getModel()->db()->whereNull('deleted_at');
        if ($kind !== null && in_array($kind, [Plugin::KIND_APP, Plugin::KIND_PLUGIN], true)) {
            $q->where('kind', $kind);
        }
        $dbRows = $q->order('id desc')->select()->toArray();

        // 已经在 DB（含软删行——避免反复 revive 干扰）的 code 集合，从虚拟行中排除
        $dbAllCodes = $this->getModel()->db()->column('code');
        $dbCodeSet = array_fill_keys(array_map('strval', $dbAllCodes), true);

        // 磁盘扫描
        $virtual = [];
        $scanner = app(PluginScanner::class);
        foreach ($scanner->scan() as $code => $manifest) {
            if (isset($dbCodeSet[$code])) {
                continue;
            }
            $manifestKind = in_array(($manifest['kind'] ?? 'plugin'), [Plugin::KIND_APP, Plugin::KIND_PLUGIN], true)
                ? (string) $manifest['kind']
                : Plugin::KIND_PLUGIN;
            if ($kind !== null && $kind !== $manifestKind) {
                continue;
            }
            $virtual[] = [
                'id'           => 0,
                'code'         => $code,
                'name'         => (string) ($manifest['name'] ?? $code),
                'version'      => (string) ($manifest['version'] ?? '0.0.0'),
                'author'       => (string) ($manifest['author'] ?? ''),
                'description'  => (string) ($manifest['description'] ?? ''),
                'icon'         => (string) ($manifest['icon'] ?? ''),
                'type'         => (int) ($manifest['type'] ?? Plugin::TYPE_BUSINESS),
                'kind'         => $manifestKind,
                'source'       => Plugin::SOURCE_BUILTIN,
                'status'       => 0, // 0 = 未安装（DB 状态常量从 1 起，0 留给虚拟态）
                'manifest'     => $manifest,
                'package_path' => '',
                'installed_at' => null,
                'last_error'   => '',
                'created_at'   => null,
                'updated_at'   => null,
            ];
        }
        usort($virtual, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));

        $merged = array_merge($dbRows, $virtual);
        $total  = count($merged);
        $offset = max(0, ($page - 1) * $limit);
        $list   = array_map(function ($row) {
            $row = $this->withIconUrl($row);
            return $this->withLocalUpdateHint($row);
        }, array_slice($merged, $offset, $limit));

        return ['list' => $list, 'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit]];
    }

    /**
     * 本地可升级提示：对比已安装版本与 runtime/plugin-packages/<code>-*.zip、磁盘 plugin.json。
     * 不覆盖 marketplace 同步写入的 update_available=1（市场源优先）。
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withLocalUpdateHint(array $row): array
    {
        $code = (string) ($row['code'] ?? '');
        $installed = (string) ($row['version'] ?? '');
        if ($code === '' || $installed === '' || (int) ($row['id'] ?? 0) === 0) {
            return $row;
        }
        // 市场同步已标记可升级时保留
        if ((int) ($row['update_available'] ?? 0) === 1 && (string) ($row['latest_version'] ?? '') !== '') {
            return $row;
        }

        $candidates = [];
        $diskJson = \think\facade\App::getRootPath() . 'plugins/' . $code . '/plugin.json';
        if (is_file($diskJson)) {
            $m = json_decode((string) file_get_contents($diskJson), true);
            if (is_array($m) && !empty($m['version'])) {
                $candidates[] = (string) $m['version'];
            }
        }
        $pkgDir = RuntimePaths::pluginPackagesDir(\think\facade\App::getRootPath());
        if (is_dir($pkgDir)) {
            foreach (glob($pkgDir . '/' . $code . '-*.zip') ?: [] as $zip) {
                $base = basename((string) $zip, '.zip');
                $prefix = $code . '-';
                if (str_starts_with($base, $prefix)) {
                    $candidates[] = substr($base, strlen($prefix));
                }
            }
        }

        $latest = $installed;
        foreach ($candidates as $v) {
            if (version_compare($v, $latest, '>')) {
                $latest = $v;
            }
        }
        if (version_compare($latest, $installed, '>')) {
            $row['latest_version']   = $latest;
            $row['update_available'] = 1;
        } else {
            $row['latest_version']   = $row['latest_version'] ?? $installed;
            $row['update_available'] = (int) ($row['update_available'] ?? 0);
        }
        return $row;
    }

    /**
     * 给定一批 code，返回其中 status = $status 的子集（全局查，不过软删）。
     * 专供依赖预检：plugins 表是全局表（tenantScoped=false），直接走 Model::db()。
     *
     * @param  string[] $codes
     * @param  int      $status Plugin::STATUS_* 常量
     * @return string[] 实际满足条件的 code 子集
     */
    public function codesInState(array $codes, int $status): array
    {
        if (empty($codes)) {
            return [];
        }
        return $this->getModel()->db()
            ->whereIn('code', $codes)
            ->where('status', $status)
            ->whereNull('deleted_at')
            ->column('code');
    }

    /**
     * 按 code 批量更新指定列，绕开软删 scope。
     * 专供安装后写回 depends 列。
     *
     * @param array<string, mixed> $data
     */
    public function updateColumnByCode(string $code, array $data): int
    {
        return (int) $this->getModel()->db()
            ->where('code', $code)
            ->update($data);
    }

    /**
     * 找出所有 depends JSON 数组中包含 $code 的已启用插件的 code 列表。
     * MySQL 8 JSON_CONTAINS 支持字符串在 JSON 数组中的查找。
     *
     * @return string[] 依赖此插件的插件 code 集合
     */
    public function findReverseDependents(string $code): array
    {
        $jsonNeedle = json_encode($code); // e.g. "mall" → "\"mall\""
        return $this->getModel()->db()
            ->whereRaw("JSON_CONTAINS(depends, ?, '$')", [$jsonNeedle])
            ->where('status', Plugin::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->column('code');
    }

    /**
     * 按 id 批量取插件名。
     * @param int[] $ids
     * @return array<int,string> id => name
     */
    public function namesByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $rows = $this->getModel()->db()->whereIn('id', $ids)->column('name', 'id');
        return array_map('strval', $rows);
    }

    /**
     * 列出所有可被授权（已启用 + 未软删）的插件，供套餐授权下拉用。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAvailableForGrant(): array
    {
        $rows = $this->getModel()->db()
            ->where('status', \app\model\plugin\Plugin::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->field('id, code, name, version, type, source, icon, description')
            ->order('id asc')
            ->select()
            ->toArray();
        return array_map(fn ($r) => $this->withIconUrl($r), $rows);
    }

    /**
     * 按权益 code / 插件 code 批量取展示元数据（name + 可访问 icon URL）。
     *
     * @param array<int, string> $codes
     * @return array<string, array{name:string,icon:string}> keyed by entitlement code（同时用 plugin.code 索引一份）
     */
    public function displayMetaByEntitlementCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
        if ($codes === []) {
            return [];
        }

        $rows = $this->getModel()->db()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($codes) {
                $q->whereIn('entitlement', $codes)->whereOr(function ($q2) use ($codes) {
                    $q2->whereIn('code', $codes);
                });
            })
            ->field('code, name, icon, entitlement')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $pluginCode = (string) ($row['code'] ?? '');
            $meta = [
                'name' => (string) ($row['name'] ?? $pluginCode),
                'icon' => \core\plugin\PluginIconResolver::iconUrl(
                    $pluginCode,
                    (string) ($row['icon'] ?? '')
                ),
            ];
            $entitlement = (string) ($row['entitlement'] ?? '');
            if ($entitlement !== '') {
                $map[$entitlement] = $meta;
            }
            if ($pluginCode !== '') {
                $map[$pluginCode] = $meta;
            }
        }

        return $map;
    }
}
