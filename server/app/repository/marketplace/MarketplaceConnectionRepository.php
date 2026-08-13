<?php

declare(strict_types=1);

namespace app\repository\marketplace;

use app\model\marketplace\MarketplaceConnection;
use core\base\Repository;
use think\Model;

class MarketplaceConnectionRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new MarketplaceConnection();
    }

    public function listNotDeleted(): array
    {
        return $this->getModel()
            ->whereNull('deleted_at')
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    public function findActive(): ?array
    {
        $row = $this->getModel()
            ->where('status', MarketplaceConnection::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->order('id', 'desc')
            ->find();
        return $row ? $row->toArray() : null;
    }

    public function findByUuid(string $uuid): ?array
    {
        $row = $this->getModel()->where('instance_uuid', $uuid)->find();
        return $row ? $row->toArray() : null;
    }

    public function markHeartbeat(int $id): void
    {
        $this->update($id, [
            'last_heartbeat_at' => date('Y-m-d H:i:s'),
            'last_error'        => null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function markSync(int $id): void
    {
        $this->update($id, [
            'last_sync_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function markError(int $id, string $err): void
    {
        $this->update($id, [
            'last_error' => mb_substr($err, 0, 500),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
