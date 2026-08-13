<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\FileCategory;
use core\base\Repository;
use think\Model;

/**
 * 文件分类仓储（自动租户隔离）
 */
class FileCategoryRepository extends Repository
{
    protected function getModel(): Model
    {
        return new FileCategory();
    }

    /** 当前租户全部分类（平铺，排序稳定） */
    public function getAllFlat(): array
    {
        return $this->query()->order('sort asc, id asc')->select()->toArray();
    }

    /** 是否存在子分类 */
    public function hasChildren(int $id): bool
    {
        return $this->query()->where('parent_id', $id)->count() > 0;
    }
}
