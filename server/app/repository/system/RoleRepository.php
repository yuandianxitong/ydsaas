<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Role;
use core\base\Repository;
use core\tenant\TenantContext;
use think\Model;

class RoleRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Role();
    }

    /**
     * 获取角色列表（包含权限统计）
     */
    public function getListWithStats(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->query()->withCount(['admins', 'menus'])->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('sort asc, created_at desc')
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => ceil($total / $limit)
            ]
        ];
    }

    /**
     * 获取角色详情（包含菜单权限）
     */
    public function getDetailWithMenus(int $id): ?array
    {
        $role = $this->query()->with(['menus'])->where('id', $id)->find();
        if (!$role) {
            return null;
        }
        return is_array($role) ? $role : $role->toArray();
    }

    /**
     * 分配菜单权限
     *
     * role_menus 是关联表，没有 tenant_id 列；通过 role_id 隐式归属当前租户
     * （roleId 必须是本租户的角色，由调用方在 RoleService 中先校验）。
     * 因此 pivot 表的写入直接走 Db::table，不应用 tenant 作用域。
     */
    public function assignMenus(int $roleId, array $menuIds): bool
    {
        $now = date('Y-m-d H:i:s');

        // 清除旧关联
        \think\facade\Db::table('role_menus')->where('role_id', $roleId)->delete();

        // 写入新关联（带时间戳）
        if (!empty($menuIds)) {
            $pivotData = array_map(fn(int $menuId) => [
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $menuIds);
            \think\facade\Db::table('role_menus')->insertAll($pivotData);
        }

        return true;
    }

    /**
     * 获取所有启用的角色
     */
    public function getAllEnabled(): array
    {
        return $this->query()->where('status', 1)
            ->order('sort asc, id asc')
            ->field('id, name, title')
            ->select()
            ->toArray();
    }

    /**
     * 检查角色名是否存在
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->query()->where('name', $name);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 检查角色是否被管理员使用（限定 tenant_id）
     */
    public function isUsedByAdmin(int $roleId): bool
    {
        $tenantId = TenantContext::current()?->id() ?? 0;
        return \think\facade\Db::table('admin_roles')
            ->where('role_id', $roleId)
            ->where('tenant_id', $tenantId)
            ->count() > 0;
    }

    /**
     * 获取角色下所有管理员ID（限定 tenant_id）
     */
    public function getAdminIdsByRoleId(int $roleId): array
    {
        $tenantId = TenantContext::current()?->id() ?? 0;
        return \think\facade\Db::table('admin_roles')
            ->where('role_id', $roleId)
            ->where('tenant_id', $tenantId)
            ->column('admin_id');
    }
}
