<?php
declare(strict_types=1);

namespace app\repository\saas;

use core\base\Repository;
use app\model\saas\TenantNotificationLog;
use think\Model;

class TenantNotificationLogRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new TenantNotificationLog();
    }

    public function hasSentToday(int $tenantId, string $notificationType): bool
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->where('notification_type', $notificationType)
            ->where('sent_date', date('Y-m-d'))
            ->count() > 0;
    }

    public function logSent(int $tenantId, string $notificationType, string $channel): void
    {
        TenantNotificationLog::create([
            'tenant_id'         => $tenantId,
            'notification_type' => $notificationType,
            'sent_date'         => date('Y-m-d'),
            'channel'           => $channel,
        ]);
    }
}
