<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\SaasOrder;
use think\Model;

class SaasOrderRepository extends Repository
{
    /**
     * saas_orders 跨平台/租户查询，关闭自动 scope。
     * 租户自己后台看自己订单由 Service 显式带 tenant_id 过滤；
     * 平台超管查所有订单时不过滤。
     */
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new SaasOrder();
    }

    public function findByOrderNo(string $orderNo): ?array
    {
        $row = $this->query()->where('order_no', $orderNo)->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * 租户自己看自己的订单（强制 tenant_id 过滤）
     */
    public function listOfTenant(int $tenantId, int $page = 1, int $size = 20): array
    {
        return $this->getList(['tenant_id' => $tenantId], $page, $size, 'id desc');
    }

    /**
     * 平台看所有订单（按 status / tenant_id 过滤，超管用）
     */
    public function paginate(array $where = [], int $page = 1, int $size = 20): array
    {
        return $this->getList($where, $page, $size, 'id desc');
    }

    /**
     * 租户插件订单（type=4）分页（不 join，插件名由 Service 二次填充）。
     *
     * @param array{status?: int|null, keyword?: string} $filters
     * @return array{list: array<int,array<string,mixed>>, pagination: array{current_page:int,per_page:int,total:int,last_page:int}}
     */
    public function listPluginOrdersOfTenant(int $tenantId, int $page, int $limit, array $filters = []): array
    {
        $build = $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('type', 4)
            ->whereNull('deleted_at');
        $status = isset($filters['status']) ? (int) $filters['status'] : 0;
        if ($status > 0) {
            $build->where('status', $status);
        }
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $build->whereLike('order_no', '%' . $keyword . '%');
        }
        $total = (clone $build)->count();
        $rows = $build->order('id', 'desc')->page($page, $limit)->select()->toArray();
        // 统一分页结构（list + pagination.{current_page,per_page,total,last_page}），对齐前端 PageResult/useListPage
        return $this->buildPagination($rows, $page, $limit, (int) $total);
    }

    public function findPendingRenewalOfTenant(int $tenantId): ?array
    {
        $row = $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->where('type', '<>', 4)
            ->where('expired_at', '>', date('Y-m-d H:i:s'))
            ->whereNull('deleted_at')
            ->order('id', 'desc')
            ->find();

        if (!$row) {
            return null;
        }

        return is_array($row) ? $row : $row->toArray();
    }
}
