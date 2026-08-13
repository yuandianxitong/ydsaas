<?php
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\PlatformAdmin;
use think\Model;

class PlatformAdminRepository extends Repository
{
    /**
     * platform_admins 表不带 tenant_id，关闭作用域。
     */
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PlatformAdmin();
    }

    /**
     * 登录用：按 username 查询，并解除 password hidden 约束以便校验。
     * 返回原始数组（含 password）。
     */
    public function findByUsername(string $username): ?array
    {
        $row = $this->query()->where('username', $username)->find();
        if (!$row) {
            return null;
        }
        // 如果是 Model 实例，解除 hidden 约束让 password 可见
        if (is_array($row)) {
            return $row;
        }
        $row->hidden([]);
        return $row->toArray();
    }

    /**
     * 按 ID 查询，返回 Model 实例（用于调用关联方法如 getPermissions）
     */
    public function findModel(int $id): ?PlatformAdmin
    {
        return PlatformAdmin::find($id);
    }

    public function updateLastLogin(int $id, string $ip): bool
    {
        return $this->query()->where('id', $id)->update([
            'last_login_ip' => $ip,
            'last_login_at' => date('Y-m-d H:i:s'),
            'login_count'   => \think\facade\Db::raw('login_count + 1'),
        ]) > 0;
    }

    /**
     * 分页搜索列表
     */
    public function getSearchList(array $params = [], int $page = 1, int $limit = 15): array
    {
        $page = (int) ($params['page'] ?? $page);
        $limit = (int) ($params['limit'] ?? $limit);

        $query = $this->query()->order('id', 'desc');

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->whereLike('username', "%{$params['keyword']}%")
                  ->whereOr('real_name', 'like', "%{$params['keyword']}%");
            });
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

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

    /**
     * 判断用户名是否已存在
     */
    public function existsUsername(string $username, int $excludeId = 0): bool
    {
        $query = $this->query()->where('username', $username);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 获取管理员关联的角色 ID 列表
     */
    public function getRoleIds(int $adminId): array
    {
        return \think\facade\Db::table('platform_admin_roles')
            ->where('platform_admin_id', $adminId)
            ->column('platform_role_id');
    }

    /**
     * 分配角色（清除旧关联 + 写入新关联）
     */
    public function assignRoles(int $adminId, array $roleIds): void
    {
        \think\facade\Db::table('platform_admin_roles')
            ->where('platform_admin_id', $adminId)
            ->delete();

        if (!empty($roleIds)) {
            $rows = array_map(fn($rid) => [
                'platform_admin_id' => $adminId,
                'platform_role_id'  => $rid,
            ], $roleIds);
            \think\facade\Db::table('platform_admin_roles')->insertAll($rows);
        }
    }

    /**
     * 清除管理员的所有角色关联
     */
    public function clearRoles(int $adminId): void
    {
        \think\facade\Db::table('platform_admin_roles')
            ->where('platform_admin_id', $adminId)
            ->delete();
    }
}
