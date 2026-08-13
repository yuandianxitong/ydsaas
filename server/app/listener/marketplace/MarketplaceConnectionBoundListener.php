<?php

declare(strict_types=1);

namespace app\listener\marketplace;

use think\facade\Log;

class MarketplaceConnectionBoundListener
{
    public function handle(array $event): void
    {
        Log::info('marketplace.connection.bound', [
            'instance_uuid' => $event['instance_uuid'] ?? null,
            'instance_name' => $event['instance_name'] ?? null,
            'token_prefix'  => $event['token_prefix'] ?? null,
            'site_base_url' => $event['site_base_url'] ?? null,
        ]);
    }
}
