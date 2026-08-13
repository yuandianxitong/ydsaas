<?php

declare(strict_types=1);

namespace app\repository\marketplace;

use app\model\marketplace\MarketplacePublicKey;
use core\base\Repository;
use think\Model;

class MarketplacePublicKeyRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new MarketplacePublicKey();
    }

    public function getValidPem(string $keyId): ?string
    {
        $row = $this->getModel()
            ->where('key_id', $keyId)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();
        return $row ? (string) $row['pem'] : null;
    }

    public function store(string $keyId, string $pem, int $ttlSeconds): void
    {
        $existing = $this->getModel()->where('key_id', $keyId)->find();
        $now      = date('Y-m-d H:i:s');
        $payload  = [
            'key_id'     => $keyId,
            'pem'        => $pem,
            'fetched_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ];
        if ($existing) {
            $this->update((int) $existing['id'], $payload);
        } else {
            $payload['created_at'] = $now;
            $this->create($payload);
        }
    }
}
