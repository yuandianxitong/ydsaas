<?php

declare(strict_types=1);

namespace app\listener\marketplace;

use think\facade\Log;

class MarketplaceInstallSucceededListener
{
    public function handle(array $event): void
    {
        Log::info('marketplace.app.installed_or_upgraded', $event);
    }
}
