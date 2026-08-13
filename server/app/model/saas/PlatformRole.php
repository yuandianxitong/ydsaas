<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;
use think\model\relation\BelongsToMany;

class PlatformRole extends Model
{
    protected $name = 'platform_roles';
    protected $autoWriteTimestamp = 'datetime';

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(PlatformAdmin::class, 'platform_admin_roles', 'platform_admin_id', 'platform_role_id');
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(PlatformMenu::class, 'platform_role_menus', 'platform_menu_id', 'platform_role_id');
    }

    public function assignMenus(array $menuIds): bool
    {
        $this->menus()->detach();
        if (!empty($menuIds)) {
            $this->menus()->attach($menuIds);
        }
        return true;
    }

    public function getMenuIds(): array
    {
        return \think\facade\Db::table('platform_role_menus')
            ->where('platform_role_id', $this->id)
            ->column('platform_menu_id');
    }
}
