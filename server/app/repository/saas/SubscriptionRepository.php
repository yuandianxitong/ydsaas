<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\Subscription;
use think\Model;

class SubscriptionRepository extends Repository
{
    /**
     * subscriptions 表有 tenant_id 列，但平台超管需要查所有租户。
     * 关闭自动 scope，由 Service 层按需显式带 tenant_id 过滤。
     */
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new Subscription();
    }

    /**
     * 获取租户当前生效的订阅（最新的 status=1 的一条）
     */
    public function currentOfTenant(int $tenantId): ?array
    {
        $row = $this->query()
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->order('id', 'desc')
            ->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * 获取租户所有历史订阅（id desc）
     */
    public function listOfTenant(int $tenantId, int $page = 1, int $size = 20): array
    {
        return $this->getList(['tenant_id' => $tenantId], $page, $size, 'id desc');
    }
}
