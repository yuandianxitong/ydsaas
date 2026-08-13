<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\File;
use core\base\Repository;
use think\Model;

class FileRepository extends Repository
{
    protected function getModel(): Model
    {
        return new File();
    }

    /**
     * 获取文件列表
     */
    public function getFileList(array $where = [], int $page = 1, int $limit = 20, string $order = 'created_at desc'): array
    {
        $query = $this->query()->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)->order($order)->select()->toArray();

        return [
            'list'       => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * 获取分组列表
     */
    public function getGroups(): array
    {
        return $this->query()
            ->field('`group`, COUNT(*) as count')
            ->group('`group`')
            ->order('count desc')
            ->select()
            ->toArray();
    }

    /**
     * 按分类统计直属文件数（当前租户），用于分类树 file_count
     */
    public function countByCategory(): array
    {
        return $this->query()
            ->field('category_id, COUNT(*) as cnt')
            ->group('category_id')
            ->select()
            ->toArray();
    }

    /**
     * 将某分类下的直属文件全部移入未分类（category_id=0）
     */
    public function moveCategoryToUncategorized(int $categoryId): int
    {
        return $this->query()->where('category_id', $categoryId)->update(['category_id' => 0]);
    }

    /**
     * 批量将文件移动到指定分类（0=未分类）
     */
    public function moveToCategory(array $ids, int $categoryId): void
    {
        $this->query()->whereIn('id', $ids)->update(['category_id' => $categoryId]);
    }
}
