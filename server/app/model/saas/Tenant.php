<?php
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class Tenant extends Model
{
    protected $name = 'tenants';
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'custom_settings' => 'json',
    ];

    protected $append = ['lifecycle_state'];

    /**
     * 计算 lifecycle_state
     * - disabled：status=0
     * - trial：当前生效订阅为试用且 expires_at > now
     * - active：expires_at > now (或 null)
     * - grace：expires_at <= now < expires_at + grace_days
     * - frozen：now >= expires_at + grace_days
     */
    public function getLifecycleStateAttr($_, $data): string
    {
        if ((int)($data['status'] ?? 1) === 0) {
            return 'disabled';
        }
        $expiresAt = $data['expires_at'] ?? null;
        if ($expiresAt === null || $expiresAt === '') {
            return 'active';  // 永不过期
        }
        $now = time();
        $expiresAtTs = strtotime((string) $expiresAt);
        if ($expiresAtTs === false) {
            return 'active';
        }
        if ($now < $expiresAtTs) {
            return $this->hasActiveTrialSubscription((int) ($data['id'] ?? 0), $now)
                ? 'trial'
                : 'active';
        }
        $graceDays = (int) \think\facade\Config::get('saas.grace_days', 7);
        if ($now < $expiresAtTs + $graceDays * 86400) {
            return 'grace';
        }
        return 'frozen';
    }

    private function hasActiveTrialSubscription(int $tenantId, int $now): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        return Subscription::where('tenant_id', $tenantId)
            ->where('status', 1)
            ->where('type', 1)
            ->where('ends_at', '>', date('Y-m-d H:i:s', $now))
            ->count() > 0;
    }
}
