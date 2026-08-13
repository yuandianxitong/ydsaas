<?php
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\PlatformRole;
use think\facade\Db;
use think\Model;

class PlatformRoleRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PlatformRole();
    }

    public function getListWithStats(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->query()
            ->withCount(['admins', 'menus']);

        if (!empty($where['keyword'])) {
            $query->whereLike('name', "%{$where['keyword']}%");
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', (int) $where['status']);
        }

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
                'last_page' => (int) ceil($total / $limit),
            ],
        ];
    }

    public function getDetailWithMenus(int $id): ?array
    {
        $role = $this->find($id);
        if (!$role) {
            return null;
        }
        $role['menu_ids'] = Db::table('platform_role_menus')
            ->where('platform_role_id', $id)
            ->column('platform_menu_id');
        return $role;
    }

    public function assignMenus(int $roleId, array $menuIds): bool
    {
        Db::table('platform_role_menus')->where('platform_role_id', $roleId)->delete();
        if (!empty($menuIds)) {
            $rows = array_map(fn($mid) => ['platform_role_id' => $roleId, 'platform_menu_id' => $mid], $menuIds);
            Db::table('platform_role_menus')->insertAll($rows);
        }
        return true;
    }

    public function getAllEnabled(): array
    {
        return $this->query()->where('status', 1)->order('sort', 'asc')->select()->toArray();
    }

    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->query()->where('name', $name);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    public function isUsedByAdmin(int $roleId): bool
    {
        return Db::table('platform_admin_roles')->where('platform_role_id', $roleId)->count() > 0;
    }
}
