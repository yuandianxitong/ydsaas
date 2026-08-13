<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\diy;

use app\model\diy\DiyLink;
use core\base\Repository;
use think\Model;

class DiyLinkRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DiyLink();
    }

    /** 当前租户全部链接（管理页用），最新在前。 */
    public function listAll(): array
    {
        return $this->query()->order('sort', 'asc')->order('id', 'desc')->select()->toArray();
    }

    /** 当前租户「已启用」链接转目录项（供 LinkCatalog 合并）。 */
    public function listLibraryLinks(): array
    {
        $rows = $this->query()->where('status', 1)->order('sort', 'asc')->select()->toArray();
        return array_map(static fn (array $r): array => [
            'label'    => (string) $r['label'],
            'path'     => (string) $r['path'],
            'category' => (string) ($r['category'] ?: '我的链接'),
        ], $rows);
    }
}
