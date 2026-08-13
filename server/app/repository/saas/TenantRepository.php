<?php
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\Tenant;
use think\Model;

class TenantRepository extends Repository
{
    /**
     * tenants 表本身没有 tenant_id 字段（它就是租户主表），关闭作用域。
     */
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new Tenant();
    }

    public function findByCode(string $code): ?array
    {
        $row = $this->query()->where('tenant_code', $code)->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * 按主键查找租户（语义化别名，供 EntitlementService 等使用）
     */
    public function findById(int $id): ?array
    {
        return $this->find($id);
    }

    /**
     * 读取租户当前套餐 ID（不存在则 0）。
     */
    public function planIdOf(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }
        $planId = $this->query()->where('id', $tenantId)->value('plan_id');

        return (int) ($planId ?? 0);
    }

    public function paginate(array $where = [], int $page = 1, int $size = 20): array
    {
        return $this->getList($where, $page, $size, 'id desc');
    }

    /** @return int[] */
    public function listIdsByPlan(int $planId): array
    {
        return $this->getModel()->where('plan_id', $planId)->whereNull('deleted_at')->column('id');
    }

    /**
     * 批量取租户名称（id => name），用于列表页避免 N+1。含软删租户（历史订单仍需展示名称）。
     *
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->getModel()->db()
            ->whereIn('id', $ids)
            ->column('name', 'id');
    }
}
