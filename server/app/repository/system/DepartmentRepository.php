<?php
declare(strict_types=1);

namespace app\repository\system;

use core\base\Model;
use core\base\Repository;
use core\tenant\TenantContext;
use app\model\system\Department;

class DepartmentRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Department();
    }

    /**
     * 获取部门树形列表
     */
    public function getTree(array $where = []): array
    {
        $query = $this->query()->where('deleted_at', null);

        if (!empty($where['status'])) {
            $query->where('status', (int) $where['status']);
        }
        if (!empty($where['keyword'])) {
            $query->where('name', 'like', '%' . $where['keyword'] . '%');
        }

        return $query->order('sort asc, id asc')->select()->toArray();
    }

    /**
     * 获取所有启用的部门（用于下拉选择）
     */
    public function getAllEnabled(): array
    {
        return $this->query()->where('status', 1)
            ->where('deleted_at', null)
            ->field('id, parent_id, name, code')
            ->order('sort asc, id asc')
            ->select()
            ->toArray();
    }

    /**
     * 检查编码是否存在
     */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $query = $this->query()->where('code', $code)->where('deleted_at', null);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 获取子部门ID列表（递归）
     */
    public function getChildIds(int $parentId): array
    {
        $all = $this->query()->where('deleted_at', null)
            ->field('id, parent_id')
            ->select()
            ->toArray();

        return $this->findChildIds($all, $parentId);
    }

    /**
     * 递归查找子级ID
     */
    protected function findChildIds(array $list, int $parentId): array
    {
        $ids = [];
        foreach ($list as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $ids[] = (int) $item['id'];
                $ids = array_merge($ids, $this->findChildIds($list, (int) $item['id']));
            }
        }
        return $ids;
    }

    /**
     * 检查部门下是否有管理员
     *
     * 跨表查询，手动应用 tenant 作用域以保持租户隔离。
     */
    public function hasAdmins(int $departmentId): bool
    {
        $query = \think\facade\Db::table('admins')
            ->where('department_id', $departmentId)
            ->where('deleted_at', null);

        if (!TenantContext::isScopeBypassed()) {
            $ctx = TenantContext::current();
            if ($ctx !== null) {
                $query->where('tenant_id', $ctx->id());
            }
        }

        return $query->count() > 0;
    }
}
