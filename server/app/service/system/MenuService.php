<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\MenuRepository;
use core\base\Service;
use core\exception\BusinessException;

class MenuService extends Service
{
    protected MenuRepository $menuRepository;

    /**
     * 获取菜单树
     */
    public function getMenuTree(bool $onlyEnabled = true): array
    {
        return $this->menuRepository->getMenuTree($onlyEnabled);
    }

    /**
     * 获取菜单选项树
     */
    public function getMenuOptions(int $excludeId = 0): array
    {
        return $this->menuRepository->getMenuOptions($excludeId);
    }

    /**
     * 获取前端路由菜单
     */
    public function getFrontendRoutes(array $menuIds = []): array
    {
        return $this->menuRepository->getFrontendRoutes($menuIds);
    }

    /**
     * 获取用户的按钮权限
     */
    public function getButtonPermissions(array $menuIds = []): array
    {
        return $this->menuRepository->getButtonPermissionsByMenuIds($menuIds);
    }

    /**
     * 创建菜单
     */
    public function createMenu(array $data): array
    {
        // 验证菜单名称和路径唯一性
        if (!empty($data['name']) && $this->menuRepository->existsName($data['name'])) {
            throw new BusinessException(lang('business.menu_name_exists'));
        }

        if (!empty($data['path']) && $this->menuRepository->existsPath($data['path'])) {
            throw new BusinessException(lang('business.route_path_exists'));
        }

        // 验证父级菜单
        if ($data['parent_id'] > 0) {
            $parent = $this->menuRepository->find($data['parent_id']);
            if (!$parent) {
                throw new BusinessException(lang('business.parent_menu_not_found'));
            }

            // 按钮类型的菜单只能是叶子节点
            if ($parent['type'] == 3) {
                throw new BusinessException(lang('business.button_no_children'));
            }
        }

        $menuData = [
            'parent_id' => $data['parent_id'] ?? 0,
            'type' => $data['type'],
            'title' => $data['title'],
            'name' => $data['name'] ?? '',
            'path' => $data['path'] ?? '',
            'component' => $data['component'] ?? '',
            'redirect' => $data['redirect'] ?? '',
            'icon' => $data['icon'] ?? '',
            'permission' => $data['permission'] ?? '',
            'is_hidden' => $data['is_hidden'] ?? 0,
            'is_cache' => $data['is_cache'] ?? 1,
            'is_affix' => $data['is_affix'] ?? 0,
            'is_iframe' => $data['is_iframe'] ?? 0,
            'external_link' => $data['external_link'] ?? '',
            'breadcrumb' => $data['breadcrumb'] ?? 1,
            'active_menu' => $data['active_menu'] ?? '',
            'meta' => $data['meta'] ?? null,
            'status' => $data['status'] ?? 1,
            'sort' => $data['sort'] ?? 0,
            'created_by' => $data['created_by'] ?? 0,
        ];

        $menu = $this->menuRepository->create($menuData);

        $this->log('创建菜单成功', ['menu_id' => $menu['id']]);

        $this->trigger('menu.changed', [
            'action'  => 'create',
            'menu_id' => $menu['id'],
        ]);

        return $menu;
    }

    /**
     * 更新菜单
     */
    public function updateMenu(int $id, array $data): bool
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            throw new BusinessException(lang('business.menu_not_found'));
        }

        // 验证菜单名称和路径唯一性
        if (isset($data['name']) && !empty($data['name']) && $this->menuRepository->existsName($data['name'], $id)) {
            throw new BusinessException(lang('business.menu_name_exists'));
        }

        if (isset($data['path']) && !empty($data['path']) && $this->menuRepository->existsPath($data['path'], $id)) {
            throw new BusinessException(lang('business.route_path_exists'));
        }

        // 验证父级菜单（不能设置自己为父级）
        if (isset($data['parent_id']) && $data['parent_id'] == $id) {
            throw new BusinessException(lang('business.parent_not_self'));
        }

        if (isset($data['parent_id']) && $data['parent_id'] > 0) {
            $parent = $this->menuRepository->find($data['parent_id']);
            if (!$parent) {
                throw new BusinessException(lang('business.parent_menu_not_found'));
            }

            // 不能设置子菜单为父级
            $allChildrenIds = $this->menuRepository->getAllChildrenIds($id);
            if (in_array($data['parent_id'], $allChildrenIds)) {
                throw new BusinessException(lang('business.parent_not_child'));
            }
        }

        $updateData = array_filter([
            'parent_id' => $data['parent_id'] ?? null,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null,
            'name' => $data['name'] ?? null,
            'path' => $data['path'] ?? null,
            'component' => $data['component'] ?? null,
            'redirect' => $data['redirect'] ?? null,
            'icon' => $data['icon'] ?? null,
            'permission' => $data['permission'] ?? null,
            'is_hidden' => $data['is_hidden'] ?? null,
            'is_cache' => $data['is_cache'] ?? null,
            'is_affix' => $data['is_affix'] ?? null,
            'is_iframe' => $data['is_iframe'] ?? null,
            'external_link' => $data['external_link'] ?? null,
            'breadcrumb' => $data['breadcrumb'] ?? null,
            'active_menu' => $data['active_menu'] ?? null,
            'meta' => $data['meta'] ?? null,
            'status' => $data['status'] ?? null,
            'sort' => $data['sort'] ?? null,
            'updated_by' => $data['updated_by'] ?? 0,
        ], function($value) {
            return $value !== null;
        });

        $result = $this->menuRepository->update($id, $updateData);

        if ($result) {
            $this->log('更新菜单成功', ['menu_id' => $id]);
            $this->trigger('menu.changed', [
                'action'  => 'update',
                'menu_id' => $id,
            ]);
        }

        return $result;
    }

    /**
     * 删除菜单
     */
    public function deleteMenu(int $id): bool
    {
        $menu = $this->menuRepository->find($id);
        if (!$menu) {
            throw new BusinessException(lang('business.menu_not_found'));
        }

        // 检查是否有子菜单
        $childrenCount = $this->menuRepository->count(['parent_id' => $id]);
        if ($childrenCount > 0) {
            throw new BusinessException(lang('business.menu_has_children'));
        }

        // 检查是否有角色使用此菜单
        if ($this->menuRepository->isUsedByRole($id)) {
            throw new BusinessException(lang('business.menu_used_by_role'));
        }

        $result = $this->menuRepository->delete($id);

        if ($result) {
            $this->log('删除菜单成功', ['menu_id' => $id]);
            $this->trigger('menu.changed', [
                'action'  => 'delete',
                'menu_id' => $id,
            ]);
        }

        return $result;
    }

    /**
     * 批量删除菜单
     *
     * 使用事务包裹：任一菜单删除失败则整体回滚，避免部分成功导致数据不一致。
     */
    public function batchDeleteMenu(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $this->runInTransaction(function () use ($ids) {
            foreach ($ids as $id) {
                $this->deleteMenu((int) $id);
            }
        });

        $this->trigger('menu.changed', [
            'action'  => 'batchDelete',
            'menu_id' => $ids,
        ]);

        return true;
    }

    /**
     * 批量排序（仅同级内）
     * @param array<int, array{id:int,parent_id:int,sort:int}> $items
     */
    public function batchSort(array $items): bool
    {
        // 基础校验
        foreach ($items as $row) {
            if (!isset($row['id'], $row['parent_id'], $row['sort'])) {
                throw new BusinessException(lang('business.sort_field_missing'));
            }
            if (!is_int($row['id']) || !is_int($row['parent_id']) || !is_int($row['sort'])) {
                throw new BusinessException(lang('business.sort_field_type_error'));
            }
        }

        // 分组：按 parent_id 分组（同级内排序）
        $groups = [];
        foreach ($items as $row) {
            $groups[$row['parent_id']][] = $row;
        }

        // 逐组校验与更新（事务）
        $this->runInTransaction(function () use ($groups) {
            foreach ($groups as $parentId => $rows) {
                $dbChildren = $this->menuRepository->getChildrenIdsByParent($parentId);

                $submitIds = array_column($rows, 'id');
                $diff = array_diff($submitIds, $dbChildren);
                if (!empty($diff)) {
                    throw new BusinessException(lang('business.sort_parent_mismatch'));
                }

                usort($rows, fn($a, $b) => $a['sort'] <=> $b['sort']);
                foreach ($rows as $idx => &$r) {
                    $r['sort'] = ($idx + 1) * 10;
                }
                unset($r);

                $this->menuRepository->batchUpdateSortCase($rows);
            }
        });

        $this->log('菜单批量排序成功', ['group_count' => count($groups)]);

        $this->trigger('menu.changed', [
            'action'  => 'sort',
            'menu_id' => null,
        ]);

        return true;
    }
}
