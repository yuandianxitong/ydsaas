<?php
declare(strict_types=1);

namespace app\job;

use app\service\saas\SaasOrderService;
use app\service\message\MessageService;
use core\tenant\TenantContext;
use think\queue\Job;
use think\facade\Db;
use think\facade\Log;

class SubscriptionAutoRenewJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            $this->run();
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('SubscriptionAutoRenewJob failed', ['error' => $e->getMessage()]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(60);
            }
        }
    }

    public function run(): void
    {
        $autoRenewDays = 3;
        $targetDate = date('Y-m-d', strtotime("+{$autoRenewDays} days"));

        $tenants = Db::table('tenants')
            ->alias('t')
            ->where('t.status', 1)
            ->whereNull('t.deleted_at')
            ->where('t.plan_id', '>', 0)
            ->whereRaw("DATE(t.expires_at) <= ?", [$targetDate])
            ->whereRaw("DATE(t.expires_at) >= ?", [date('Y-m-d')])
            ->whereNotExists(function ($query) {
                $query->table('saas_orders')
                    ->whereRaw('saas_orders.tenant_id = t.id')
                    ->where('saas_orders.status', 1)
                    ->where('saas_orders.type', 2);
            })
            ->field('t.id, t.name, t.plan_id, t.contact_email, t.contact_phone, t.expires_at')
            ->select()
            ->toArray();

        if (empty($tenants)) {
            Log::info('SubscriptionAutoRenewJob: no tenants to auto-renew');
            return;
        }

        /** @var SaasOrderService $orderService */
        $orderService = app(SaasOrderService::class);

        foreach ($tenants as $tenant) {
            try {
                $this->createRenewalOrder($tenant, $orderService);
            } catch (\Throwable $e) {
                Log::error("Auto-renew failed for tenant {$tenant['id']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function createRenewalOrder(array $tenant, SaasOrderService $orderService): void
    {
        TenantContext::runAs((int) $tenant['id'], function () use ($tenant, $orderService) {
            $order = $orderService->createOrder(
                (int) $tenant['id'],
                (int) $tenant['plan_id'],
                1,  // months: minimum period
                2   // type: renewal
            );

            Log::info("Auto-renewal order created for tenant {$tenant['id']}", [
                'order_no' => $order['order_no'] ?? '',
            ]);

            $this->notifyTenant($tenant, $order);
        });
    }

    private function notifyTenant(array $tenant, array $order): void
    {
        /** @var MessageService $messageService */
        $messageService = app(MessageService::class);

        $receivers = array_filter([
            'email' => $tenant['contact_email'] ?? '',
            'phone' => $tenant['contact_phone'] ?? '',
        ]);

        if (empty($receivers)) {
            return;
        }

        $messageService->trySend('subscription_auto_renew', $receivers, [
            'tenant_name' => $tenant['name'] ?? '',
            'order_no'    => $order['order_no'] ?? '',
            'amount'      => $order['amount'] ?? 0,
            'expire_date' => $tenant['expires_at'] ?? '',
        ]);
    }
}
