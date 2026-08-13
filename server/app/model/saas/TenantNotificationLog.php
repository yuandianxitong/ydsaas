<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class TenantNotificationLog extends Model
{
    protected $name = 'tenant_notification_logs';
    protected $autoWriteTimestamp = 'datetime';
    protected $updateTime = false;
}
