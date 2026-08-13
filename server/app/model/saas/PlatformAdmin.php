<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;
use think\model\relation\BelongsToMany;

class PlatformAdmin extends Model
{
    protected $name = 'platform_admins';
    protected $autoWriteTimestamp = 'datetime';
    protected $hidden = ['password'];

    // ---- Relationships ----

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_admin_roles', 'platform_role_id', 'platform_admin_id');
    }

    // ---- Permission helpers ----

    /**
     * Get all permission strings from menus in assigned roles.
     * Super admin gets ['*'].
     *
     * v2.7.2：含 type=2（菜单条目）+ type=3（按钮）。type=2 行的 permission 字段
     * 是真实接口权限（每个菜单对应一个 Controller 入口，如 plan.view /
     * platform.plugin.list / platform.refund.list 等），controllers 的 #[Permission]
     * 注解直接指向这些 code；旧实现只过 type=3 → 非超管即使分到菜单也调不通
     * list/view 接口（403）。
     */
    public function getPermissions(): array
    {
        if ($this->is_super) {
            return ['*'];
        }

        $menuIds = [];
        foreach ($this->roles as $role) {
            $roleMenuIds = \think\facade\Db::table('platform_role_menus')
                ->where('platform_role_id', $role->id)
                ->column('platform_menu_id');
            $menuIds = array_merge($menuIds, $roleMenuIds);
        }
        $menuIds = array_unique($menuIds);

        if (empty($menuIds)) {
            return [];
        }

        return \think\facade\Db::table('platform_menus')
            ->whereIn('id', $menuIds)
            ->whereIn('type', [2, 3])
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('permission', '<>', '')
            ->column('permission');
    }

    /**
     * Get all menu IDs (type 1+2) from assigned roles.
     * Super admin gets all menus.
     */
    public function getMenuIds(): array
    {
        if ($this->is_super) {
            return \think\facade\Db::table('platform_menus')
                ->whereIn('type', [1, 2])
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->column('id');
        }

        $menuIds = [];
        foreach ($this->roles as $role) {
            $roleMenuIds = \think\facade\Db::table('platform_role_menus')
                ->where('platform_role_id', $role->id)
                ->column('platform_menu_id');
            $menuIds = array_merge($menuIds, $roleMenuIds);
        }
        return array_values(array_unique($menuIds));
    }

    public function hasPermission(string $permission): bool
    {
        $perms = $this->getPermissions();
        return in_array('*', $perms) || in_array($permission, $perms);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, (string) $this->password);
    }
}
