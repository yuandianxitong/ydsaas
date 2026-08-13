<?php
declare(strict_types=1);

namespace app\job;

use app\repository\saas\TenantNotificationLogRepository;
use app\service\message\MessageService;
use think\queue\Job;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class SubscriptionReminderJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            $this->run();
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('SubscriptionReminderJob failed', ['error' => $e->getMessage()]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(60);
            }
        }
    }

    public function run(): void
    {
        $reminderDays = Config::get('saas.reminder_days', [14, 7, 3, 1]);
        $graceDays = Config::get('saas.grace_days', 7);

        /** @var TenantNotificationLogRepository $logRepo */
        $logRepo = app(TenantNotificationLogRepository::class);

        foreach ($reminderDays as $days) {
            $days = (int) $days;
            $targetDate = date('Y-m-d', strtotime("+{$days} days"));
            $notificationType = "expire_{$days}d";
            $this->notifyTenantsByExpireDate($targetDate, $notificationType, $days, $logRepo);
        }

        // Grace period entered (expired today)
        $this->notifyTenantsByExpireDate(date('Y-m-d'), 'grace_entered', 0, $logRepo);

        // Grace period ending
        $graceEndTarget = date('Y-m-d', strtotime("-" . ($graceDays - 1) . " days"));
        $this->notifyTenantsByExpireDate($graceEndTarget, 'grace_ending', -($graceDays - 1), $logRepo);
    }

    private function notifyTenantsByExpireDate(
        string $targetDate,
        string $notificationType,
        int $daysLeft,
        TenantNotificationLogRepository $logRepo
    ): void {
        $tenants = Db::table('tenants')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereRaw("DATE(expires_at) = ?", [$targetDate])
            ->field('id, name, contact_email, contact_phone, expires_at')
            ->select()
            ->toArray();

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];

            if ($logRepo->hasSentToday($tenantId, $notificationType)) {
                Log::info("Tenant {$tenantId} already notified [{$notificationType}] today, skip");
                continue;
            }

            $this->sendReminder($tenant, $notificationType, $daysLeft);
            $logRepo->logSent($tenantId, $notificationType, 'email');
            Log::info("Tenant {$tenantId} notified [{$notificationType}]");
        }
    }

    private function sendReminder(array $tenant, string $notificationType, int $daysLeft): void
    {
        /** @var MessageService $messageService */
        $messageService = app(MessageService::class);

        $receivers = array_filter([
            'email' => $tenant['contact_email'] ?? '',
            'phone' => $tenant['contact_phone'] ?? '',
        ]);

        if (empty($receivers)) {
            Log::warning("Tenant {$tenant['id']} has no contact info, skip notification");
            return;
        }

        $messageService->trySend('subscription_reminder', $receivers, [
            'tenant_name' => $tenant['name'] ?? '',
            'expire_date' => $tenant['expires_at'] ?? '',
            'days_left'   => (string) abs($daysLeft),
            'type'        => $notificationType,
        ]);
    }
}
