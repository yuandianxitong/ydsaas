<?php
declare(strict_types=1);

namespace app\job;

use app\repository\message\MessageLogRepository;
use think\queue\Job;
use think\facade\Event;
use think\facade\Log;
use think\facade\Db;

/**
 * 订阅到期预警检查 Job
 *
 * 通过 QueueManager::push() 或 cron 调度触发。
 * 检查距到期日 N 天的租户（默认 7/3/1 天），
 * 每个租户每天只推送一次（基于 message_logs 去重）。
 *
 * 调用示例：
 *   \core\queue\QueueManager::push(\app\job\SubscriptionExpiryCheckJob::class, []);
 */
class SubscriptionExpiryCheckJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            $this->run();
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('SubscriptionExpiryCheckJob 执行失败', ['error' => $e->getMessage()]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(60);
            }
        }
    }

    public function run(): void
    {
        $warningDays = \think\facade\Config::get('saas.expiry_warning_days', [7, 3, 1]);
        if (!is_array($warningDays)) {
            $warningDays = [7, 3, 1];
        }

        /** @var MessageLogRepository $logRepo */
        $logRepo = app(MessageLogRepository::class);

        foreach ($warningDays as $days) {
            $days = (int) $days;
            $targetDate = date('Y-m-d', strtotime("+{$days} days"));

            // 查找 expires_at 日期匹配的租户
            $tenants = Db::table('tenants')
                ->where('status', 1)
                ->whereRaw("DATE(expires_at) = ?", [$targetDate])
                ->field('id, name, expires_at')
                ->select()
                ->toArray();

            foreach ($tenants as $tenant) {
                $tenantId   = (int) $tenant['id'];
                $tenantName = $tenant['name'] ?? '';

                // 今日去重：检查 message_logs 是否已推送过 subscription_expiring
                $todayStart = date('Y-m-d') . ' 00:00:00';
                $todayEnd   = date('Y-m-d') . ' 23:59:59';
                $alreadySent = Db::table('message_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('template_code', 'subscription_expiring')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->count();

                if ($alreadySent > 0) {
                    Log::info("租户 {$tenantId} 今日已推送订阅到期预警，跳过");
                    continue;
                }

                // 查找关联的套餐名称
                $subscription = Db::table('subscriptions')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 1)
                    ->order('id', 'desc')
                    ->find();

                $planName = '当前套餐';
                if ($subscription) {
                    $plan = Db::table('plans')->where('id', $subscription['plan_id'] ?? 0)->find();
                    $planName = $plan['name'] ?? $planName;
                }

                Event::trigger('message.event.dispatch', [
                    'event_code' => 'subscription_expiring',
                    'tenant_id'  => $tenantId,
                    'receivers'  => [],
                    'variables'  => [
                        'tenant_name' => $tenantName,
                        'plan_name'   => $planName,
                        'expire_date' => $tenant['expires_at'] ?? $targetDate,
                        'days_left'   => (string) $days,
                    ],
                ]);

                Log::info("已触发租户 {$tenantId} 订阅到期预警（{$days}天）");
            }
        }
    }
}
