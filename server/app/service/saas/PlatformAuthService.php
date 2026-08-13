<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\auth\TokenManager;
use core\exception\BusinessException;
use app\repository\saas\PlatformAdminRepository;
use think\facade\Db;
use think\facade\Request;

/**
 * 平台超管认证 Service
 *
 * 物理隔离：所有 token 操作走 TokenManager::scope('platform')。
 * Repository 也强制 tenantScoped=false（platform_admins 没有 tenant_id）。
 */
class PlatformAuthService extends Service
{
    protected PlatformAdminRepository $platformAdminRepository;

    /**
     * 平台超管登录
     */
    public function login(string $username, string $password, string $ip): array
    {
        $admin = $this->platformAdminRepository->findByUsername($username);
        if (!$admin) {
            $this->writeLoginLog(0, $ip, 0, '账号或密码错误');
            throw new BusinessException('账号或密码错误');
        }
        if ((int) ($admin['status'] ?? 0) !== 1) {
            $this->writeLoginLog((int) $admin['id'], $ip, 0, '账号已禁用');
            throw new BusinessException('账号已禁用');
        }
        if (!password_verify($password, (string) $admin['password'])) {
            $this->writeLoginLog((int) $admin['id'], $ip, 0, '账号或密码错误');
            throw new BusinessException('账号或密码错误');
        }

        // 更新最后登录信息（失败不阻塞登录）
        try {
            $this->platformAdminRepository->updateLastLogin((int) $admin['id'], $ip);
        } catch (\Throwable $e) {
            $this->log('platform admin updateLastLogin failed: ' . $e->getMessage(), [], 'warning');
        }

        // 写登录日志（同步落库；失败不阻塞登录）
        $this->writeLoginLog((int) $admin['id'], $ip, 1, '登录成功');

        $token = TokenManager::scope('platform')->generate([
            'platform_admin_id' => (int) $admin['id'],
            'username'          => (string) $admin['username'],
        ]);

        unset($admin['password']);
        return [
            'token' => $token,
            'admin' => $admin,
        ];
    }

    /**
     * 写平台登录日志。status: 1=成功 0=失败
     */
    private function writeLoginLog(int $adminId, string $ip, int $status, string $message): void
    {
        try {
            Db::table('platform_login_logs')->insert([
                'platform_admin_id' => $adminId,
                'ip'                => $ip,
                'user_agent'        => Request::header('user-agent', ''),
                'status'            => $status,
                'message'           => mb_substr($message, 0, 250),
                'created_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->log('platform login log write failed: ' . $e->getMessage(), [], 'warning');
        }
    }

    /**
     * 当前登录用户信息
     */
    public function me(int $id): array
    {
        if ($id <= 0) {
            throw new BusinessException('账号不存在');
        }
        $admin = $this->platformAdminRepository->find($id);
        if (!$admin) {
            throw new BusinessException('账号不存在');
        }
        unset($admin['password']);
        return $admin;
    }

    /**
     * 获取当前平台超管信息 + 菜单 + 权限（RBAC）
     */
    public function info(int $platformAdminId): array
    {
        if ($platformAdminId <= 0) {
            throw new BusinessException('账号不存在');
        }
        $admin = $this->platformAdminRepository->findModel($platformAdminId);
        if (!$admin) {
            throw new BusinessException('账号不存在');
        }

        $permissions = $admin->getPermissions();
        $menuIds = $admin->getMenuIds();

        // Load menus: super admin gets all, others get role-assigned menus
        $menuRepo = app(\app\repository\saas\PlatformMenuRepository::class);
        $routes = $menuRepo->getFrontendRoutes($admin->is_super ? [] : $menuIds);

        $adminArr = $admin->toArray();
        unset($adminArr['password']);
        $adminArr['role_ids'] = $this->platformAdminRepository->getRoleIds($platformAdminId);

        return [
            'admin'       => $adminArr,
            'routes'      => $routes,
            'permissions' => $permissions,
            'saas'        => ['is_platform' => true],
        ];
    }

    /**
     * 注销：将当前 token 加入黑名单
     */
    public function logout(string $token): void
    {
        if ($token === '') {
            return;
        }
        TokenManager::scope('platform')->blacklist($token);
    }
}
