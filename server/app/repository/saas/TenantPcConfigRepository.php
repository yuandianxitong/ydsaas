<?php

declare(strict_types=1);

namespace app\repository\saas;

use app\model\saas\TenantPcConfig;
use core\base\Repository;
use think\Model;

class TenantPcConfigRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new TenantPcConfig();
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
     * @param array<string, mixed> $data
     */
    public function upsert(int $tenantId, array $data): array
    {
        $existing = $this->query()->where('tenant_id', $tenantId)->find();
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $data['updated_at'] = $now;
            $this->query()->where('tenant_id', $tenantId)->update($data);
        } else {
            $data['tenant_id'] = $tenantId;
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $data['status'] = $data['status'] ?? 1;
            $this->query()->insert($data);
        }

        return $this->findByTenantId($tenantId) ?? [];
    }
}
