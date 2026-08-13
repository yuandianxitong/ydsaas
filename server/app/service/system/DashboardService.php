<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\model\saas\TenantMobileBuild;
use app\model\system\SystemConfig;
use app\repository\saas\TenantMobileBuildRepository;
use app\repository\saas\TenantPcConfigRepository;
use app\repository\system\AdminRepository;
use app\repository\system\AdminLoginLogRepository;
use app\repository\system\RoleRepository;
use app\repository\system\MenuRepository;
use app\repository\user\UserRepository;
use app\repository\system\AdminOperationLogRepository;
use app\repository\system\SystemConfigRepository;
use core\base\Service;
use core\helper\DateHelper;
use core\tenant\TenantContext;
use think\facade\Cache;
use think\facade\Config;

class DashboardService extends Service
{
    protected AdminRepository $adminRepository;
    protected AdminLoginLogRepository $loginLogRepository;
    protected RoleRepository $roleRepository;
    protected MenuRepository $menuRepository;
    protected UserRepository $userRepository;
    protected AdminOperationLogRepository $operationLogRepository;
    protected SystemConfigRepository $systemConfigRepository;
    protected TenantMobileBuildRepository $mobileBuildRepository;
    protected TenantPcConfigRepository $pcConfigRepository;

    /**
     * 获取仪表板统计数据（5分钟缓存）
     * @param int $days 趋势天数（7=本周, 30=本月）
     */
    public function getStats(int $days = 7): array
    {
        $tenantId = TenantContext::current()?->id() ?? 0;
        $cacheKey = 'dashboard_stats_' . $tenantId . '_' . $days;
        return Cache::remember($cacheKey, function () use ($days) {
            $totalUsers = $this->userRepository->getTotalCount();
            $activeUsers = $this->userRepository->getActiveCount(7);
            $todayNewUsers = $this->userRepository->getTodayNewCount();
            $todayLoginCount = $this->loginLogRepository->getTodaySuccessCount();

            // 环比趋势（与上周同日对比）
            $lastWeekNewUsers = $this->userRepository->getLastWeekSameDayNewCount();
            $lastWeekLogin = $this->loginLogRepository->getLastWeekSameDaySuccessCount();
            $lastWeekActive = $this->userRepository->getLastWeekActiveCount();

            return [
                'adminCount'       => $this->adminRepository->count(),
                'roleCount'        => $this->roleRepository->count(),
                'menuCount'        => $this->menuRepository->count(),
                'configCount'      => $this->systemConfigRepository->getTotalCount(),
                'todayLoginCount'  => $todayLoginCount,
                'todayNewUsers'    => $todayNewUsers,
                'activeUsers'      => $activeUsers,
                'totalUsers'       => $totalUsers,
                'trends' => [
                    'totalUsers'      => $this->calcTrend($totalUsers, $totalUsers - $todayNewUsers + $lastWeekNewUsers),
                    'activeUsers'     => $this->calcTrendPercent($activeUsers, $lastWeekActive),
                    'todayNewUsers'   => $this->calcTrend($todayNewUsers, $lastWeekNewUsers),
                    'todayLoginCount' => $this->calcTrend($todayLoginCount, $lastWeekLogin),
                ],
                'operationLogCount' => $this->operationLogRepository->getTodayCount(),
                'loginTrend'       => $this->loginLogRepository->getRecentTrend($days, true),
                'registerTrend'    => $this->userRepository->getRegisterTrend($days),
            ];
        }, 300);
    }

    /**
     * 获取最近登录日志
     */
    public function getRecentLogs(int $limit = 10): array
    {
        return $this->loginLogRepository->getRecentLogs($limit);
    }

    /**
     * 获取最近动态（合并登录日志 + 操作日志）
     */
    public function getRecentActivities(int $limit = 8): array
    {
        $loginLogs = $this->loginLogRepository->getRecentLogs(5);
        $operationLogs = $this->operationLogRepository->getRecentActivities(5);

        $activities = [];

        foreach ($loginLogs as $log) {
            $activities[] = [
                'type'          => $log['login_result'] ? 'login_success' : 'login_failed',
                'username'      => $log['username'],
                'description'   => $log['username'] . ($log['login_result'] ? ' 登录系统' : ' 登录失败'),
                'time'          => $log['login_time'],
                'relative_time' => DateHelper::diffForHumans($log['login_time']),
            ];
        }

        foreach ($operationLogs as $log) {
            $activities[] = [
                'type'          => 'operation',
                'username'      => $log['username'],
                'description'   => $log['username'] . ' ' . ($log['description'] ?: $log['action']),
                'time'          => $log['operation_time'],
                'relative_time' => DateHelper::diffForHumans($log['operation_time']),
            ];
        }

        usort($activities, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));

        return array_slice($activities, 0, $limit);
    }

    /**
     * 获取活跃排行
     */
    public function getActiveRanking(string $period = 'day', int $limit = 10): array
    {
        $list = $this->loginLogRepository->getActiveRanking($period, $limit);

        $ranked = [];
        foreach ($list as $index => $item) {
            $ranked[] = [
                'rank'     => $index + 1,
                'username' => $item['username'],
                'count'    => (int) $item['count'],
            ];
        }

        return [
            'period' => $period,
            'list'   => $ranked,
        ];
    }

    /**
     * 工作台「店铺访问」：H5 / 小程序 / PC 入口就绪态。
     *
     * @return array{
     *   h5: array{ready: bool, reason_code: string, url: string, action_path: string},
     *   miniprogram: array{ready: bool, reason_code: string, qr_url: string, action_path: string},
     *   pc: array{ready: bool, reason_code: string, url: string, action_path: string}
     * }
     */
    public function getAccessInfo(): array
    {
        $ctx = TenantContext::current();
        $tenantId = $ctx?->id() ?? 0;
        $tenantCode = $ctx?->code() ?? '';

        return [
            'h5'          => $this->buildH5Access($tenantId, $tenantCode),
            'miniprogram' => $this->buildMiniprogramAccess(),
            'pc'          => $this->buildPcAccess($tenantId, $tenantCode),
        ];
    }

    /**
     * 租户前台规范域名：{scheme}://{tenant_code}.{root}/{path}/
     * path 例：mobile、pc
     */
    private function buildTenantPortalUrl(string $tenantCode, string $path): ?string
    {
        $rootDomains = array_values(array_filter(array_map(
            'strval',
            (array) Config::get('saas.root_domains', [])
        )));
        $root = $rootDomains[0] ?? '';
        if ($tenantCode === '' || $root === '') {
            return null;
        }

        $scheme = 'https';
        if ((bool) Config::get('app.app_debug', false)) {
            $req = request();
            $scheme = (string) ($req->header('x-forwarded-proto') ?: $req->scheme() ?: 'https');
        }

        $path = trim($path, '/');

        return $scheme . '://' . $tenantCode . '.' . $root . '/' . $path . '/';
    }

    /**
     * @return array{ready: bool, reason_code: string, url: string, action_path: string}
     */
    private function buildH5Access(int $tenantId, string $tenantCode): array
    {
        $action = '/diy/build';
        $url = $this->buildTenantPortalUrl($tenantCode, 'mobile');

        if ($tenantId <= 0 || $url === null) {
            return [
                'ready'       => false,
                'reason_code' => 'no_domain',
                'url'         => '',
                'action_path' => $action,
            ];
        }

        $released = $this->mobileBuildRepository->findLatestByPlatform(
            $tenantId,
            'h5',
            [TenantMobileBuild::STATUS_RELEASED]
        );
        if ($released !== null) {
            return [
                'ready'       => true,
                'reason_code' => '',
                'url'         => $url,
                'action_path' => $action,
            ];
        }

        $hasBuildArtifact = $this->mobileBuildRepository->hasByPlatformStatuses(
            $tenantId,
            'h5',
            [
                TenantMobileBuild::STATUS_SUCCESS,
                TenantMobileBuild::STATUS_UPLOADED,
            ]
        );

        return [
            'ready'       => false,
            'reason_code' => $hasBuildArtifact ? 'not_released' : 'not_built',
            'url'         => '',
            'action_path' => $action,
        ];
    }

    /**
     * PC 门户与 H5 同域规则，路径为 /pc/。仅已配置且启用的门户可访问；
     * 编译产物部署由基础设施负责，当前代码没有可用的部署探测能力。
     *
     * @return array{ready: bool, reason_code: string, url: string, action_path: string}
     */
    private function buildPcAccess(int $tenantId, string $tenantCode): array
    {
        $action = '/diy/pc';
        $url = $this->buildTenantPortalUrl($tenantCode, 'pc');
        if ($tenantId <= 0 || $url === null) {
            return [
                'ready'       => false,
                'reason_code' => 'no_domain',
                'url'         => '',
                'action_path' => $action,
            ];
        }

        $config = $this->pcConfigRepository->findByTenantId($tenantId);
        if ($config === null) {
            return [
                'ready'       => false,
                'reason_code' => 'not_configured',
                'url'         => '',
                'action_path' => $action,
            ];
        }

        if ((int) ($config['status'] ?? 0) !== 1) {
            return [
                'ready'       => false,
                'reason_code' => 'disabled',
                'url'         => '',
                'action_path' => $action,
            ];
        }

        return [
            'ready'       => true,
            'reason_code' => '',
            'url'         => $url,
            'action_path' => $action,
        ];
    }

    /**
     * @return array{ready: bool, reason_code: string, qr_url: string, action_path: string}
     */
    private function buildMiniprogramAccess(): array
    {
        $action = '/channel/miniapp/config';
        $qr = trim((string) SystemConfig::getConfigValue('wechat_mini_qrcode', ''));
        if ($qr === '') {
            return [
                'ready'       => false,
                'reason_code' => 'no_qrcode',
                'qr_url'      => '',
                'action_path' => $action,
            ];
        }

        if (!preg_match('#^https?://#i', $qr)) {
            $siteUrl = rtrim((string) SystemConfig::getConfigValue('site_url', ''), '/');
            if ($siteUrl !== '') {
                $qr = $siteUrl . '/' . ltrim($qr, '/');
            }
        }

        return [
            'ready'       => true,
            'reason_code' => '',
            'qr_url'      => $qr,
            'action_path' => $action,
        ];
    }

    /**
     * 计算趋势（绝对值差）
     */
    private function calcTrend(int $current, int $previous): array
    {
        $diff = $current - $previous;
        return [
            'value' => abs($diff),
            'type'  => $diff >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * 计算趋势（百分比）
     */
    private function calcTrendPercent(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['value' => $current > 0 ? 100 : 0, 'type' => 'up', 'unit' => 'percent'];
        }
        $percent = round(($current - $previous) / $previous * 100, 2);
        return [
            'value' => abs($percent),
            'type'  => $percent >= 0 ? 'up' : 'down',
            'unit'  => 'percent',
        ];
    }

}
