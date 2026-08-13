<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\FileCategoryRepository;
use app\repository\system\FileRepository;
use core\base\Service;
use core\exception\BusinessException;

/**
 * 文件分类服务：无限层级分类树（租户隔离）
 */
class FileCategoryService extends Service
{
    protected FileCategoryRepository $fileCategoryRepo;
    protected FileRepository $fileRepo;

    /** 嵌套分类树，节点含直属文件数 file_count */
    public function getTree(): array
    {
        $rows = $this->fileCategoryRepo->getAllFlat();
        if (empty($rows)) {
            return [];
        }

        // 各分类直属文件数（一次聚合查询）
        $counts = [];
        $countRows = $this->fileRepo->countByCategory();
        foreach ($countRows as $r) {
            $counts[(int) $r['category_id']] = (int) $r['cnt'];
        }

        $nodes = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $nodes[$id] = [
                'id' => $id,
                'parent_id' => (int) $row['parent_id'],
                'name' => $row['name'],
                'sort' => (int) $row['sort'],
                'file_count' => $counts[$id] ?? 0,
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $pid = $node['parent_id'];
            if ($pid > 0 && isset($nodes[$pid])) {
                $nodes[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }

    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new BusinessException('分类名称不能为空且不超过100字');
        }
        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0 && !$this->fileCategoryRepo->find($parentId)) {
            throw new BusinessException('父分类不存在');
        }

        return $this->fileCategoryRepo->create([
            'parent_id' => $parentId,
            'name' => $name,
            'sort' => (int) ($data['sort'] ?? 0),
        ]);
    }

    public function rename(int $id, string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new BusinessException('分类名称不能为空且不超过100字');
        }
        if (!$this->fileCategoryRepo->find($id)) {
            throw new BusinessException('分类不存在');
        }
        $this->fileCategoryRepo->update($id, ['name' => $name]);
    }

    public function delete(int $id): void
    {
        if (!$this->fileCategoryRepo->find($id)) {
            throw new BusinessException('分类不存在');
        }
        if ($this->fileCategoryRepo->hasChildren($id)) {
            throw new BusinessException('请先删除子分类');
        }

        $this->runInTransaction(function () use ($id) {
            // 直属文件移入未分类
            $this->fileRepo->moveCategoryToUncategorized($id);
            $this->fileCategoryRepo->delete($id);
        });
    }
}
