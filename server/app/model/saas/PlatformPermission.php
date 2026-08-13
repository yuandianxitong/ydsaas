<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class PlatformPermission extends Model
{
    protected $name = 'platform_permissions';
    protected $autoWriteTimestamp = 'datetime';
}
