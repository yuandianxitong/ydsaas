<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\repository\saas;

use app\model\saas\TenantMobileConfig;
use core\base\Repository;
use think\Model;

class TenantMobileConfigRepository extends Repository
{
    /**
     * 该表已有 tenant_id 一对一索引，关闭自动作用域避免与显式查询冲突。
     */
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new TenantMobileConfig();
    }

    public function findByTenantId(int $tenantId): ?array
    {
        $row = $this->query()->where('tenant_id', $tenantId)->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * upsert：存在则更新，不存在则插入。
     * @param array<string, mixed> $data 已规范化好的字段（含 tabbar_json 反序列化前的数组）
     */
    public function upsert(int $tenantId, array $data): array
    {
        $existing = $this->query()->where('tenant_id', $tenantId)->find();
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            $data['updated_at'] = $now;
            $this->query()->where('tenant_id', $tenantId)->update($data);
        } else {
            $data['tenant_id']  = $tenantId;
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $data['status'] = $data['status'] ?? 1;
            $this->query()->insert($data);
        }

        return $this->findByTenantId($tenantId) ?? [];
    }
}
