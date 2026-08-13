<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\Plan;
use think\Model;

class PlanRepository extends Repository
{
    /**
     * plans 表不带 tenant_id（全平台共享），关闭作用域。
     */
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new Plan();
    }

    public function findByCode(string $code): ?array
    {
        $row = $this->query()->where('code', $code)->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    public function paginate(array $where = [], int $page = 1, int $size = 20): array
    {
        return $this->getList($where, $page, $size, 'sort asc,id asc');
    }

    public function listActive(): array
    {
        return $this->query()
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 批量取套餐名称（id => name），用于列表页避免 N+1。
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
