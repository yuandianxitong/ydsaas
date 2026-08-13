<?php
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use core\cache\TagCache;
use app\model\saas\PlatformMenu;
use think\facade\Db;
use think\Model;

class PlatformMenuRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PlatformMenu();
    }

    private const CACHE_TAG = 'platform_menu';
    private const CACHE_TTL = 3600;

    public function getMenuTree(bool $onlyEnabled = true): array
    {
        $cacheKey = 'platform_menu_tree_' . ($onlyEnabled ? '1' : '0');
        return TagCache::remember(self::CACHE_TAG, $cacheKey, function () use ($onlyEnabled) {
            $query = $this->query()->order('sort', 'asc');
            if ($onlyEnabled) {
                $query->where('status', 1);
            }
            $rows = $query->select()->toArray();
            $menus = array_map(function ($row) {
                $row['title'] = $row['name'];
                return $row;
            }, $rows);
            return \core\helper\ArrayHelper::toTree($menus, 'id', 'parent_id', 'children', 0);
        }, self::CACHE_TTL);
    }

    public function getFrontendRoutes(array $menuIds = []): array
    {
        $query = $this->query()
            ->where('status', 1)
            ->order('sort', 'asc');

        if (!empty($menuIds)) {
            $allIds = array_merge($menuIds, $this->getButtonIdsByParentIds($menuIds));
            $query->whereIn('id', array_unique($allIds));
        }

        $rows = $query->select()->toArray();

        // 扁平 → 树形
        $mapped = [];
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            $type = (int) $row['type'];

            // 目录类型(type=1)如果 path 为空，自动生成路径，否则前端会过滤掉
            if ($path === '' && $type === 1) {
                $path = '/dir-' . $row['id'];
            }

            $mapped[(int) $row['id']] = [
                'id'         => (int) $row['id'],
                'parent_id'  => (int) $row['parent_id'],
                'type'       => $type,
                'title'      => (string) $row['name'],
                'name'       => $this->menuNameFromPath($path),
                'path'       => $path,
                'component'  => (string) $row['component'],
                'icon'       => (string) $row['icon'],
                'sort'       => (int) $row['sort'],
                'permission' => (string) $row['permission'],
                'hidden'     => (int) $row['hidden'],
                'status'     => (int) $row['status'],
                'children'   => [],
            ];
        }

        $tree = [];
        foreach ($mapped as &$item) {
            $pid = $item['parent_id'];
            if ($pid === 0 || !isset($mapped[$pid])) {
                $tree[] = &$item;
            } else {
                $mapped[$pid]['children'][] = &$item;
            }
        }
        unset($item);

        return $tree;
    }

    public function getMenuOptions(int $excludeId = 0): array
    {
        $query = $this->query()
            ->whereIn('type', [1, 2])
            ->where('status', 1)
            ->order('sort', 'asc');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        $rows = $query->select()->toArray();
        return array_map(function ($row) {
            $row['title'] = $row['name'];
            return $row;
        }, $rows);
    }

    public function getButtonPermissionsByMenuIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        return Db::table('platform_menus')
            ->whereIn('parent_id', $menuIds)
            ->where('type', 3)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('permission', '<>', '')
            ->column('permission');
    }

    public function clearCache(): void
    {
        TagCache::clear(self::CACHE_TAG);
    }

    private function getButtonIdsByParentIds(array $parentIds): array
    {
        return Db::table('platform_menus')
            ->whereIn('parent_id', $parentIds)
            ->where('type', 3)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->column('id');
    }

    /**
     * 检查菜单是否有子菜单
     */
    public function hasChildren(int $id): bool
    {
        return $this->getModel()->db()
            ->where('parent_id', $id)
            ->whereNull('deleted_at')
            ->count() > 0;
    }

    private function menuNameFromPath(string $path): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '/'], ' ', $path)));
    }
}
