<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\marketplace;

use core\base\Service;

/**
 * 纯函数 license 评估器：给定 plugin / connection / cache，返回目标 license_status。
 * 状态机转换的实际持久化由 LicenseStateMachine 负责。
 */
class RuntimeLicenseEvaluator extends Service
{
    /**
     * 决定 plugin 当前应处于什么 license_status。
     *
     * 优先级：
     * 1. cache 缺失（无连接 / sync 删除该 entitlement）→ grace 或 expired
     * 2. Site 长时间不可达（last_sync_at 超过 site_unreachable_grace_trigger_hours）
     * 3. entitlement.period_end 已过
     * 否则 → active
     */
    public function determineTarget(array $plugin, ?array $conn, ?array $cache, int $now): string
    {
        $graceDays        = (int) config('saas.marketplace.grace_period_days', 14);
        $unreachableHours = (int) config('saas.marketplace.site_unreachable_grace_trigger_hours', 24);
        $graceStartedStr  = $plugin['license_grace_started_at'] ?? null;
        $graceStartedTs   = $graceStartedStr ? strtotime((string) $graceStartedStr) : null;

        $expired = $graceStartedTs !== null && ($graceStartedTs + $graceDays * 86400) < $now;

        // 优先级 1: 无连接或 cache 缺失 → entitlement 撤销
        if ($conn === null || $cache === null) {
            return $expired ? 'expired' : 'grace';
        }

        // 优先级 2: Site 长时间不可达
        $lastSyncTs = isset($conn['last_sync_at']) ? strtotime((string) $conn['last_sync_at']) : 0;
        if ($lastSyncTs > 0 && ($now - $lastSyncTs) > $unreachableHours * 3600) {
            return $expired ? 'expired' : 'grace';
        }

        // 优先级 3: entitlement period_end 过期
        if (!empty($cache['period_end'])) {
            $periodEnd = strtotime((string) $cache['period_end']);
            if ($periodEnd > 0 && $periodEnd < $now) {
                return $expired ? 'expired' : 'grace';
            }
        }

        return 'active';
    }

    /**
     * 计算 active → grace 转换时 grace_started_at 应当写入的时间。
     * - Site 不可达场景：last_sync_at + unreachable trigger hours（不是 now）
     * - entitlement period_end 过期：period_end
     * - entitlement 撤销 / 其他：now
     *
     * 保留 plugin 维度做将来 per-plugin grace policy 留口（Spec C-2）。
     */
    public function computeGraceStart(?array $conn, ?array $cache, int $now): string
    {
        // 场景 A: Site 不可达
        if ($cache !== null && $conn !== null && !empty($conn['last_sync_at'])) {
            $unreachableHours = (int) config('saas.marketplace.site_unreachable_grace_trigger_hours', 24);
            $lastSyncTs = strtotime((string) $conn['last_sync_at']);
            if ($lastSyncTs > 0 && ($now - $lastSyncTs) > $unreachableHours * 3600) {
                return date('Y-m-d H:i:s', $lastSyncTs + $unreachableHours * 3600);
            }
        }

        // 场景 B: entitlement period_end 已过
        if ($cache !== null && !empty($cache['period_end'])) {
            $periodEnd = strtotime((string) $cache['period_end']);
            if ($periodEnd > 0 && $periodEnd < $now) {
                return date('Y-m-d H:i:s', $periodEnd);
            }
        }

        // 场景 C: entitlement 撤销 / fallback → now
        return date('Y-m-d H:i:s', $now);
    }
}
