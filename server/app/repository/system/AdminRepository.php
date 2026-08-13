<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Admin;
use core\base\Repository;
use core\tenant\TenantContext;
use think\Model;

class AdminRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Admin();
    }

    /**
     * 根据用户名查找管理员
     */
    public function findByUsername(string $username): ?array
    {
        // 登录前 TenantContextMiddleware 已设置上下文，因此 query() 自动按
        // 当前租户过滤；不同租户允许同名 admin。
        $result = $this->query()->where('username', $username)->find();
        if (!$result) {
            return null;
        }
        if (is_array($result)) {
            return $result;
        }
        // 登录校验需要访问加密密码，临时清空隐藏字段再转为数组
        $result->hidden([]);
        return $result->toArray();
    }

    /**
     * 根据邮箱查找管理员
     */
    public function findByEmail(string $email): ?array
    {
        $result = $this->query()->where('email', $email)->find();
        if (!$result) {
            return null;
        }
        return is_array($result) ? $result : $result->toArray();
    }

    /**
     * 获取管理员列表（包含角色）
     */
    public function getListWithRoles(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->query()->with(['roles'])->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('created_at desc')
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
     * 获取管理员详情（包含角色和权限，使用 eager loading）
     */
    public function getDetailWithPermissions(int $id): ?array
    {
        $admin = $this->query()->with(['roles.menus'])->where('id', $id)->find();

        if (!$admin) {
            return null;
        }

        return is_array($admin) ? $admin : $admin->toArray();
    }

    /**
     * 更新最后登录信息（原子递增 login_count）
     */
    public function updateLastLogin(int $id, string $ip): bool
    {
        return $this->query()->where('id', $id)->update([
            'last_login_ip' => $ip,
            'last_login_time' => date('Y-m-d H:i:s'),
            'login_count' => \think\facade\Db::raw('login_count + 1'),
        ]) !== false;
    }

    /**
     * 分配角色
     *
     * pivot 表 admin_roles 显式写入 tenant_id，与 TenantInitService 保持一致。
     */
    public function assignRoles(int $adminId, array $roleIds): bool
    {
        $now = date('Y-m-d H:i:s');
        $tenantId = TenantContext::current()?->id() ?? 0;

        // 清除旧关联（限定 tenant_id 防止跨租户误删）
        \think\facade\Db::table('admin_roles')
            ->where('admin_id', $adminId)
            ->where('tenant_id', $tenantId)
            ->delete();

        // 写入新关联（带 tenant_id 和时间戳）
        if (!empty($roleIds)) {
            $pivotData = array_map(fn(int $roleId) => [
                'admin_id'   => $adminId,
                'role_id'    => $roleId,
                'tenant_id'  => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $roleIds);
            \think\facade\Db::table('admin_roles')->insertAll($pivotData);
        }

        return true;
    }

    /**
     * 检查用户名是否存在
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
     * 检查邮箱是否存在
     */
    public function existsEmail(string $email, int $excludeId = 0): bool
    {
        $query = $this->query()->where('email', $email);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 按租户 ID 查找管理员（平台端跨租户操作，绕过 tenant scope）
     */
    public function findByTenant(int $tenantId, int $adminId): ?array
    {
        $row = $this->getModel()->db()
            ->where('id', $adminId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * 更新管理员密码（平台端跨租户操作）
     */
    public function updatePasswordByTenant(int $tenantId, int $adminId, string $hashedPassword): bool
    {
        return $this->getModel()->db()
            ->where('id', $adminId)
            ->where('tenant_id', $tenantId)
            ->update([
                'password'   => $hashedPassword,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) !== false;
    }

    /**
     * 获取租户下的管理员列表（平台端跨租户操作）
     */
    public function listByTenant(int $tenantId): array
    {
        return $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->field('id,tenant_id,username,nickname,status,last_login_ip,last_login_time,created_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }
}
