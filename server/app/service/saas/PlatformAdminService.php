<?php
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\saas\PlatformAdminRepository;

class PlatformAdminService extends Service
{
    protected PlatformAdminRepository $platformAdminRepository;

    public function getAdminList(array $params): array
    {
        return $this->platformAdminRepository->getSearchList($params);
    }

    public function getAdminDetail(int $id): ?array
    {
        $admin = $this->platformAdminRepository->find($id);
        if (!$admin) {
            return null;
        }
        $admin['role_ids'] = $this->platformAdminRepository->getRoleIds($id);
        return $admin;
    }

    public function createAdmin(array $data): array
    {
        if ($this->platformAdminRepository->existsUsername($data['username'])) {
            throw new BusinessException('用户名已存在');
        }
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['is_super'] = 0;

        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);

        return $this->runInTransaction(function () use ($data, $roleIds) {
            $admin = $this->platformAdminRepository->create($data);
            if (!empty($roleIds)) {
                $this->platformAdminRepository->assignRoles($admin['id'], $roleIds);
            }
            return $admin;
        });
    }

    public function updateAdmin(int $id, array $data): bool
    {
        $admin = $this->platformAdminRepository->findModel($id);
        if (!$admin) {
            throw new BusinessException('管理员不存在');
        }
        if ($admin->is_super && isset($data['status']) && (int) $data['status'] !== 1) {
            throw new BusinessException('不能禁用超级管理员');
        }

        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        $roleIds = $data['role_ids'] ?? null;
        unset($data['role_ids']);

        return $this->runInTransaction(function () use ($id, $data, $roleIds) {
            $this->platformAdminRepository->update($id, $data);
            if ($roleIds !== null) {
                $this->platformAdminRepository->assignRoles($id, $roleIds);
            }
            return true;
        });
    }

    public function deleteAdmin(int $id, int $currentAdminId): bool
    {
        if ($id === $currentAdminId) {
            throw new BusinessException('不能删除自己');
        }
        $admin = $this->platformAdminRepository->findModel($id);
        if (!$admin) {
            throw new BusinessException('管理员不存在');
        }
        if ($admin->is_super) {
            throw new BusinessException('不能删除超级管理员');
        }
        return $this->runInTransaction(function () use ($id) {
            $this->platformAdminRepository->clearRoles($id);
            return $this->platformAdminRepository->delete($id);
        });
    }

    public function updateStatus(int $id, int $status): bool
    {
        $admin = $this->platformAdminRepository->findModel($id);
        if (!$admin) {
            throw new BusinessException('管理员不存在');
        }
        if ($admin->is_super) {
            throw new BusinessException('不能修改超级管理员状态');
        }
        return $this->platformAdminRepository->update($id, ['status' => $status]);
    }

    public function resetPassword(int $id, string $password): bool
    {
        return $this->platformAdminRepository->update($id, [
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function changePassword(int $id, string $oldPassword, string $newPassword): bool
    {
        $admin = $this->platformAdminRepository->findModel($id);
        if (!$admin || !password_verify($oldPassword, $admin->password)) {
            throw new BusinessException('原密码错误');
        }
        return $this->platformAdminRepository->update($id, [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
    }
}
