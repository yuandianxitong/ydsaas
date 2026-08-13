<?php
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\saas\PlatformMenuRepository;

class PlatformMenuService extends Service
{
    protected PlatformMenuRepository $platformMenuRepository;

    public function getMenuTree(bool $onlyEnabled = true): array
    {
        return $this->platformMenuRepository->getMenuTree($onlyEnabled);
    }

    public function getMenuOptions(int $excludeId = 0): array
    {
        return $this->platformMenuRepository->getMenuOptions($excludeId);
    }

    public function getFrontendRoutes(array $menuIds = []): array
    {
        return $this->platformMenuRepository->getFrontendRoutes($menuIds);
    }

    public function getButtonPermissions(array $menuIds = []): array
    {
        return $this->platformMenuRepository->getButtonPermissionsByMenuIds($menuIds);
    }

    public function createMenu(array $data): array
    {
        $data = $this->normalizeMenuData($data);
        $result = $this->platformMenuRepository->create($data);
        $this->platformMenuRepository->clearCache();
        return $result;
    }

    public function updateMenu(int $id, array $data): bool
    {
        $data = $this->normalizeMenuData($data);
        $result = $this->platformMenuRepository->update($id, $data);
        $this->platformMenuRepository->clearCache();
        return $result;
    }

    /**
     * 规范化菜单数据：提取 meta 中的 hidden 字段，移除非数据库字段
     */
    private function normalizeMenuData(array $data): array
    {
        // meta.hidden → hidden（DB 列）
        if (isset($data['meta']) && is_array($data['meta'])) {
            if (array_key_exists('hidden', $data['meta'])) {
                $data['hidden'] = $data['meta']['hidden'] ? 1 : 0;
            }
        }
        // 移除非数据库字段
        unset($data['title'], $data['children'], $data['meta'], $data['id'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
        return $data;
    }

    public function deleteMenu(int $id): bool
    {
        if ($this->platformMenuRepository->hasChildren($id)) {
            throw new BusinessException('该菜单下有子菜单，不能删除');
        }
        $result = $this->platformMenuRepository->delete($id);
        $this->platformMenuRepository->clearCache();
        return $result;
    }

    public function batchSort(array $items): bool
    {
        foreach ($items as $item) {
            $this->platformMenuRepository->update((int) $item['id'], ['sort' => (int) $item['sort']]);
        }
        $this->platformMenuRepository->clearCache();
        return true;
    }

    public function updateStatus(int $id, int $status): bool
    {
        $result = $this->platformMenuRepository->update($id, ['status' => $status]);
        $this->platformMenuRepository->clearCache();
        return $result;
    }
}
