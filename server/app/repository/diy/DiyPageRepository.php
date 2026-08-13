<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyPage;
use core\base\Repository;
use think\facade\Db;
use think\Model;

class DiyPageRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DiyPage();
    }

    /** 当前租户某 slug 的 uniapp 装修行（不存在/已软删返回 null）。query() 已自动注入 tenant_id 与软删过滤。 */
    public function findByKey(string $key): ?array
    {
        $row = $this->query()
            ->where('page_key', $key)
            ->where('platform', 'uniapp')
            ->find();
        return $row ? $row->toArray() : null;
    }

    /** 当前租户某 slug + platform 行。 */
    public function findByKeyPlatform(string $key, string $platform = 'uniapp'): ?array
    {
        $row = $this->query()
            ->where('page_key', $key)
            ->where('platform', $platform)
            ->find();
        return $row ? $row->toArray() : null;
    }

    /**
     * 导出用：当前租户全部未软删装修页（含系统页）。
     * @return array<int,array<string,mixed>>
     */
    public function listAllForExport(): array
    {
        return $this->query()
            ->order('platform', 'asc')
            ->order('page_type', 'asc')
            ->order('page_key', 'asc')
            ->select()
            ->toArray();
    }

    /** home 薄包装（兼容既有调用）。 */
    public function findHome(): ?array
    {
        return $this->findByKey('home');
    }

    /** 某页状态摘要（published 优先计数，行不存在返回 null）。 */
    public function getPageSummary(string $key): ?array
    {
        $row = $this->query()
            ->where('page_key', $key)
            ->where('platform', 'uniapp')
            ->field(['title', 'updated_at'])
            ->fieldRaw('COALESCE(JSON_LENGTH(components_published), JSON_LENGTH(components_draft), 0) AS component_count')
            ->fieldRaw('(components_published IS NOT NULL AND JSON_LENGTH(components_published) > 0) AS published')
            ->find();

        return $row ? $row->toArray() : null;
    }

    /** home 薄包装（兼容既有调用）。 */
    public function getHomeSummary(): ?array
    {
        return $this->getPageSummary('home');
    }

    /**
     * 按显式 tenant_id + slug 查行（不依赖 ambient TenantContext，供 c-api/构建队列无上下文场景）。
     * 直接走 Db::table 绕过 query() 自动租户作用域，避免与显式 tenant_id 冲突。
     */
    public function findByKeyForTenant(int $tenantId, string $key, string $platform = 'uniapp'): ?array
    {
        $row = Db::table('diy_pages')
            ->where('tenant_id', $tenantId)
            ->where('page_key', $key)
            ->where('platform', $platform)
            ->whereNull('deleted_at')
            ->find();
        if (!$row) {
            return null;
        }
        foreach (['components_draft', 'components_published', 'page_settings'] as $k) {
            if (isset($row[$k]) && is_string($row[$k])) {
                $row[$k] = $row[$k] !== '' ? json_decode($row[$k], true) : [];
            } elseif (!isset($row[$k]) || $row[$k] === null) {
                // 与 findByKey（Model 解码）保持结构一致：NULL json 列归一为 []
                $row[$k] = [];
            }
        }
        return $row;
    }

    /** home 薄包装。 */
    public function findHomeByTenant(int $tenantId): ?array
    {
        return $this->findByKeyForTenant($tenantId, 'home');
    }

    /** 原子发布：components_draft -> components_published（当前租户某 slug 行）。返回影响行数。 */
    public function publishByKey(string $key): int
    {
        return $this->query()
            ->where('page_key', $key)
            ->where('platform', 'uniapp')
            ->update([
                'components_published' => Db::raw('`components_draft`'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /** home 薄包装。 */
    public function publishHome(): int
    {
        return $this->publishByKey('home');
    }

    /** 当前租户「已启用自定义页」转链接项：/pages/diy/index?key=<slug>。供链接目录用。 */
    public function listCustomLinkPages(): array
    {
        $rows = $this->query()
            ->where('page_type', 'custom')
            ->where('platform', 'uniapp')
            ->where('status', 1)
            ->order('updated_at', 'desc')
            ->field(['page_key', 'title'])
            ->select()
            ->toArray();
        return array_map(static fn (array $r): array => [
            'label' => (string) ($r['title'] ?? $r['page_key']),
            'path'  => '/pages/diy/index?key=' . $r['page_key'],
        ], $rows);
    }

    /** 当前租户自定义页列表（不含 home），最新在前。含派生 component_count / published。 */
    /**
     * 自定义页分页列表。
     * @param bool|null $published null=全部；true=已发布；false=草稿
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function listPages(int $page = 1, int $limit = 10, string $keyword = '', ?bool $published = null): array
    {
        $base = function () use ($keyword, $published) {
            $q = $this->query()
                ->where('page_type', 'custom')
                ->where('platform', 'uniapp');
            if ($keyword !== '') {
                $q->whereLike('title', '%' . $keyword . '%');
            }
            if ($published === true) {
                $q->whereRaw('components_published IS NOT NULL AND JSON_LENGTH(components_published) > 0');
            } elseif ($published === false) {
                $q->whereRaw('(components_published IS NULL OR JSON_LENGTH(components_published) = 0)');
            }
            return $q;
        };

        $total = $base()->count();
        $rows = $base()
            ->order('updated_at', 'desc')
            ->page($page, $limit)
            ->field(['id', 'page_key', 'title', 'status', 'updated_at'])
            ->fieldRaw('COALESCE(JSON_LENGTH(components_published), JSON_LENGTH(components_draft), 0) AS component_count')
            ->fieldRaw('(components_published IS NOT NULL AND JSON_LENGTH(components_published) > 0) AS published')
            ->select()
            ->toArray();

        $list = array_map(static function (array $r): array {
            $r['component_count'] = (int) ($r['component_count'] ?? 0);
            // 先转 int 再转 bool：部分 PDO 驱动把布尔表达式返回为字符串 '0'，(bool)'0' 在 PHP 为 true
            $r['published'] = (bool) (int) ($r['published'] ?? 0);
            return $r;
        }, $rows);

        return ['list' => $list, 'total' => $total];
    }

    /** slug 是否已被当前租户占用（排除软删行，故软删后可复用）。 */
    public function existsKey(string $key): bool
    {
        return $this->query()
            ->where('page_key', $key)
            ->where('platform', 'uniapp')
            ->count() > 0;
    }

    /** 改标题。 */
    public function renamePage(int $id, string $title): void
    {
        $this->update($id, ['title' => $title, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** 改 slug。 */
    public function updateSlug(int $id, string $key): void
    {
        $this->update($id, ['page_key' => $key, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** 改启停状态。 */
    public function setStatus(int $id, int $status): void
    {
        $this->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * 软删（写 deleted_at）。
     * 同时把 page_key 改为唯一墓碑值（del_<id>_<原slug>，截断 64）以释放原 slug——
     * 唯一键 (tenant_id,page_key,platform) 含软删行，不改名会阻塞同名页重建。
     */
    public function softDelete(int $id): void
    {
        $now = date('Y-m-d H:i:s');
        $this->update($id, [
            'page_key'   => Db::raw("LEFT(CONCAT('del_', `id`, '_', `page_key`), 64)"),
            'deleted_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** 写一条版本快照，version_no = 该 page 现有最大值+1。返回 version_no。 */
    public function insertVersion(int $pageId, array $components, array $pageSettings, int $createdBy, string $note = ''): int
    {
        $tenantId = \core\tenant\TenantContext::current()?->id() ?? 0;
        $no = (int) Db::table('diy_page_versions')->where('page_id', $pageId)->max('version_no') + 1;
        $note = mb_substr(trim($note), 0, 255);
        Db::table('diy_page_versions')->insert([
            'tenant_id' => $tenantId, 'page_id' => $pageId, 'version_no' => $no,
            'components' => json_encode($components, JSON_UNESCAPED_UNICODE),
            'page_settings' => json_encode($pageSettings, JSON_UNESCAPED_UNICODE),
            'note' => $note, 'created_by' => $createdBy, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $no;
    }

    /** 当前租户某 page 的版本列表（最新在前），json 解码为数组。 */
    public function listVersions(int $pageId): array
    {
        $tenantId = \core\tenant\TenantContext::current()?->id() ?? 0;
        $rows = Db::table('diy_page_versions')
            ->where('tenant_id', $tenantId)->where('page_id', $pageId)
            ->order('version_no', 'desc')->select()->toArray();
        foreach ($rows as &$r) {
            $r['components'] = $r['components'] ? json_decode($r['components'], true) : [];
            $r['page_settings'] = $r['page_settings'] ? json_decode($r['page_settings'], true) : [];
        }
        return $rows;
    }

    /** 取某版本（租户隔离），json 解码。 */
    public function findVersion(int $versionId): ?array
    {
        $tenantId = \core\tenant\TenantContext::current()?->id() ?? 0;
        $row = Db::table('diy_page_versions')->where('tenant_id', $tenantId)->where('id', $versionId)->find();
        if (!$row) {
            return null;
        }
        $row['components'] = $row['components'] ? json_decode($row['components'], true) : [];
        $row['page_settings'] = $row['page_settings'] ? json_decode($row['page_settings'], true) : [];
        return $row;
    }

    /** 把组件树写回某 page 的草稿（restore）。 */
    public function restoreDraft(int $pageId, array $components, array $pageSettings): void
    {
        $this->update($pageId, [
            'components_draft' => json_encode($components, JSON_UNESCAPED_UNICODE),
            'page_settings' => json_encode($pageSettings, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
