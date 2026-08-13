<?php

declare(strict_types=1);

namespace app\repository\marketplace;

use app\model\marketplace\MarketplaceAppCache;
use core\base\Repository;
use think\Model;
use think\facade\Db;

class MarketplaceAppCacheRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new MarketplaceAppCache();
    }

    public function upsert(int $connectionId, string $entitlementCode, array $data): void
    {
        $existing = $this->getModel()
            ->where('connection_id', $connectionId)
            ->where('entitlement_code', $entitlementCode)
            ->find();

        $now = date('Y-m-d H:i:s');
        $data['connection_id']    = $connectionId;
        $data['entitlement_code'] = $entitlementCode;
        $data['updated_at']       = $now;

        if ($existing) {
            $this->update((int) $existing['id'], $data);
        } else {
            $data['created_at'] = $now;
            $this->create($data);
        }
    }

    public function deleteMissing(int $connectionId, array $keepCodes): int
    {
        $q = Db::name('marketplace_app_cache')->where('connection_id', $connectionId);
        if (!empty($keepCodes)) {
            $q = $q->whereNotIn('entitlement_code', $keepCodes);
        }
        return $q->delete();
    }

    public function listByConnection(int $connectionId): array
    {
        return $this->getModel()->where('connection_id', $connectionId)->order('app_name', 'asc')->select()->toArray();
    }

    public function findByEntitlement(int $connectionId, string $entitlementCode): ?array
    {
        $row = $this->getModel()
            ->where('connection_id', $connectionId)
            ->where('entitlement_code', $entitlementCode)
            ->find();
        return $row ? $row->toArray() : null;
    }

    public function findByAppCode(int $connectionId, string $appCode): ?array
    {
        if ($appCode === '') {
            return null;
        }
        $row = $this->getModel()
            ->where('connection_id', $connectionId)
            ->where('app_code', $appCode)
            ->find();
        return $row ? $row->toArray() : null;
    }

    public function findByConnectionAndRemoteApp(int $connectionId, string $remoteAppId): ?array
    {
        if ($remoteAppId === '') {
            return null;
        }
        $row = $this->getModel()
            ->where('connection_id', $connectionId)
            ->where('remote_app_id', $remoteAppId)
            ->find();
        return $row ? $row->toArray() : null;
    }
}
