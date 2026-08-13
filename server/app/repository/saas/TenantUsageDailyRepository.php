<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\TenantUsageDaily;
use think\Model;

class TenantUsageDailyRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new TenantUsageDaily();
    }

    /**
     * upsert：如果 (tenant_id, date) 已存在则更新，否则插入
     */
    public function upsert(int $tenantId, string $date, array $metrics): void
    {
        $existing = $this->query()
            ->where('tenant_id', $tenantId)
            ->where('date', $date)
            ->find();

        if ($existing) {
            $this->query()
                ->where('tenant_id', $tenantId)
                ->where('date', $date)
                ->update($metrics);
        } else {
            $this->create(array_merge([
                'tenant_id' => $tenantId,
                'date'      => $date,
            ], $metrics));
        }
    }
}
