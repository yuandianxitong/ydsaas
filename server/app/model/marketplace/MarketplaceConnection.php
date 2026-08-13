<?php

declare(strict_types=1);

namespace app\model\marketplace;

use think\Model;

class MarketplaceConnection extends Model
{
    protected $name = 'marketplace_connections';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REVOKED   = 'revoked';
}
