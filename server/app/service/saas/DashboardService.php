<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use app\repository\saas\TenantRepository;
use app\repository\saas\PlanRepository;
use app\repository\saas\PlatformAdminRepository;
use think\facade\Cache;
use think\facade\Db;

class DashboardService extends Service
{
    private const CACHE_TTL = 60;

    protected TenantRepository $tenantRepository;
    protected PlanRepository $planRepository;
    protected PlatformAdminRepository $platformAdminRepository;

    /**
     * 平台聚合统计。
     *
     * 使用 Db::table 直查做复杂 conditional where —— 这是允许的例外：
     * DashboardService 是 platform 统计用，不是业务 Repository；
     * tenantRepository/planRepository 等都 tenantScoped=false，
     * 本来就不会应用 scope；Db::table 更灵活地表达 conditional where。
     */
    public function stats(): array
    {
        return Cache::remember('platform_dashboard_stats', function () {
            return $this->computeStats();
        }, self::CACHE_TTL);
    }

    /**
     * 扩展统计：包含 stats() 全部字段 + 收入、套餐分布、存储 TOP10。
     */
    public function extendedStats(): array
    {
        return Cache::remember('platform_dashboard_extended_stats', function () {
            $base = $this->computeStats();

            // 本月 / 上月 / 本年收入：一次扫描按区间聚合
            $monthStart     = date('Y-m-01 00:00:00');
            $lastMonthStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
            $lastMonthEnd   = date('Y-m-t 23:59:59', strtotime('last month'));
            $yearStart      = date('Y-01-01 00:00:00');

            $revenueRow = Db::table('saas_orders')
                ->where('status', 2)
                ->whereNull('deleted_at')
                ->where('paid_at', '>=', $yearStart)
                ->field([
                    "SUM(CASE WHEN paid_at >= '{$monthStart}' THEN amount ELSE 0 END) AS monthly_revenue",
                    "SUM(CASE WHEN paid_at >= '{$lastMonthStart}' AND paid_at <= '{$lastMonthEnd}' THEN amount ELSE 0 END) AS last_month_revenue",
                    'SUM(amount) AS yearly_revenue',
                ])
                ->find();

            // 套餐分布：join tenants + plans，按 plan_id 分组
            $planDistribution = Db::table('tenants')
                ->alias('t')
                ->join('plans p', 't.plan_id = p.id')
                ->where('t.deleted_at', null)
                ->field('p.name as name, COUNT(t.id) as count')
                ->group('t.plan_id')
                ->select()
                ->toArray();

            // 即将过期 7/30 天：一次扫描条件聚合
            $now      = date('Y-m-d H:i:s');
            $in7Days  = date('Y-m-d H:i:s', strtotime('+7 days'));
            $in30Days = date('Y-m-d H:i:s', strtotime('+30 days'));
            $expiringRow = Db::table('tenants')
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>=', $now)
                ->where('expires_at', '<=', $in30Days)
                ->field([
                    "SUM(CASE WHEN expires_at <= '{$in7Days}' THEN 1 ELSE 0 END) AS expiring_7d",
                    'COUNT(*) AS expiring_30d',
                ])
                ->find();

            // 存储用量 TOP10
            $storageTop10 = Db::table('tenants')
                ->whereNull('deleted_at')
                ->field('id, name, storage_used_bytes, storage_limit_bytes')
                ->order('storage_used_bytes', 'desc')
                ->limit(10)
                ->select()
                ->toArray();

            return array_merge($base, [
                'monthly_revenue'    => (float) ($revenueRow['monthly_revenue'] ?? 0),
                'last_month_revenue' => (float) ($revenueRow['last_month_revenue'] ?? 0),
                'yearly_revenue'     => (float) ($revenueRow['yearly_revenue'] ?? 0),
                'plan_distribution'  => $planDistribution,
                'expiring_7d'        => (int) ($expiringRow['expiring_7d'] ?? 0),
                'expiring_30d'       => (int) ($expiringRow['expiring_30d'] ?? 0),
                'storage_top10'      => $storageTop10,
            ]);
        }, self::CACHE_TTL);
    }

    /**
     * 收入趋势：过去 N 个月每月的订单总额（status=2 已支付）。
     *
     * @param int $months 月数，默认 12
     * @return array {month: string, amount: float}[]
     */
    public function revenueTrend(int $months = 12): array
    {
        $months = max(1, min($months, 36));
        $cacheKey = 'platform_dashboard_revenue_trend_' . $months;

        return Cache::remember($cacheKey, function () use ($months) {
            $startTs    = strtotime('-' . ($months - 1) . ' months');
            $rangeStart = date('Y-m-01 00:00:00', $startTs);

            $rows = Db::table('saas_orders')
                ->where('status', 2)
                ->whereNull('deleted_at')
                ->where('paid_at', '>=', $rangeStart)
                ->field("DATE_FORMAT(paid_at, '%Y-%m') as month, SUM(amount) as amount")
                ->group("DATE_FORMAT(paid_at, '%Y-%m')")
                ->select()
                ->toArray();

            $byMonth = [];
            foreach ($rows as $row) {
                $byMonth[(string) $row['month']] = (float) $row['amount'];
            }

            $trend = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $label   = date('Y-m', strtotime("-{$i} months"));
                $trend[] = [
                    'month'  => $label,
                    'amount' => $byMonth[$label] ?? 0.0,
                ];
            }

            return $trend;
        }, self::CACHE_TTL);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $tenantTotal = $this->tenantRepository->count();
        $planTotal   = $this->planRepository->count(['status' => 1]);
        $pluginTotal = Db::table('plugins')->where('status', 1)->whereNull('deleted_at')->count();
        $adminTotal  = $this->platformAdminRepository->count(['status' => 1]);

        // 活跃租户：status=1 且 (expires_at 为空 或 expires_at > now - grace_days)
        $graceDays = (int) \think\facade\Config::get('saas.grace_days', 7);
        $frozenThreshold = date('Y-m-d H:i:s', strtotime("-{$graceDays} days"));
        $activeCount = Db::table('tenants')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($frozenThreshold) {
                $q->whereNull('expires_at')
                  ->whereOr('expires_at', '>', $frozenThreshold);
            })
            ->count();

        // 即将过期：status=1 且 expires_at 在过去 或 未来 7 天内
        $expiringThreshold = date('Y-m-d H:i:s', strtotime('+7 days'));
        $expiringCount = Db::table('tenants')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $expiringThreshold)
            ->count();

        // 最近 7 天新增租户趋势：一次 GROUP BY + PHP 补零
        $trendStart = date('Y-m-d', strtotime('-6 days'));
        $trendRows = Db::table('tenants')
            ->where('created_at', '>=', $trendStart . ' 00:00:00')
            ->field('DATE(created_at) as date, COUNT(*) as count')
            ->group('DATE(created_at)')
            ->select()
            ->toArray();

        $byDate = [];
        foreach ($trendRows as $row) {
            $byDate[(string) $row['date']] = (int) $row['count'];
        }

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $trend[] = [
                'date'  => $day,
                'count' => $byDate[$day] ?? 0,
            ];
        }

        return [
            'tenant_total'    => $tenantTotal,
            'tenant_active'   => $activeCount,
            'tenant_expiring' => $expiringCount,
            'plan_total'      => $planTotal,
            'plugin_total'    => $pluginTotal,
            'admin_total'     => $adminTotal,
            'tenant_trend'    => $trend,
        ];
    }
}
