<?php
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\saas\PlatformRoleRepository;
use app\repository\saas\PlatformMenuRepository;

class PlatformRoleService extends Service
{
    protected PlatformRoleRepository $platformRoleRepository;
    protected PlatformMenuRepository $platformMenuRepository;

    public function getRoleList(array $params): array
    {
        return $this->platformRoleRepository->getListWithStats($params);
    }

    public function getAllRoleOptions(): array
    {
        return $this->platformRoleRepository->getAllEnabled();
    }

    public function createRole(array $data): array
    {
        if ($this->platformRoleRepository->existsName($data['name'])) {
            throw new BusinessException('角色名称已存在');
        }
        $menuIds = $data['menu_ids'] ?? [];
        unset($data['menu_ids']);

        $role = $this->platformRoleRepository->create($data);
        if (!empty($menuIds)) {
            $this->platformRoleRepository->assignMenus((int) $role['id'], $menuIds);
        }
        return $role;
    }

    public function updateRole(int $id, array $data): bool
    {
        if ($this->platformRoleRepository->existsName($data['name'] ?? '', $id)) {
            throw new BusinessException('角色名称已存在');
        }
        $menuIds = $data['menu_ids'] ?? null;
        unset($data['menu_ids']);

        $this->platformRoleRepository->update($id, $data);
        if ($menuIds !== null) {
            $this->platformRoleRepository->assignMenus($id, $menuIds);
            $this->platformMenuRepository->clearCache();
        }
        return true;
    }

    public function deleteRole(int $id): bool
    {
        if ($this->platformRoleRepository->isUsedByAdmin($id)) {
            throw new BusinessException('该角色正在被使用，不能删除');
        }
        $this->platformRoleRepository->assignMenus($id, []);
        return $this->platformRoleRepository->delete($id);
    }

    public function getRolePermissions(int $id): array
    {
        return $this->platformRoleRepository->getDetailWithMenus($id) ?? [];
    }

    public function assignPermissions(int $id, array $menuIds): bool
    {
        // 平台管理员角色（code=platform_admin）天然拥有全部权限，分配菜单无意义且会误导
        $role = $this->platformRoleRepository->find($id);
        if (!$role) {
            throw new BusinessException('角色不存在', 404);
        }
        if (($role['code'] ?? '') === 'platform_admin') {
            throw new BusinessException('平台管理员角色拥有全部权限，无需分配', 403);
        }
        $this->platformRoleRepository->assignMenus($id, $menuIds);
        $this->platformMenuRepository->clearCache();
        return true;
    }

    public function updateStatus(int $id, int $status): bool
    {
        return $this->platformRoleRepository->update($id, ['status' => $status]);
    }
}
