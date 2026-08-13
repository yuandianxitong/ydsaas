<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\attribute\Permission;
use core\base\Controller;
use app\service\saas\DashboardService;
use think\Request;
use think\Response;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    #[Permission('platform.dashboard.view')]
    public function stats(): Response
    {
        return $this->success(lang('messages.get_success'), $this->dashboardService->stats());
    }

    #[Permission('platform.dashboard.view')]
    public function extendedStats(): Response
    {
        return $this->success('', $this->dashboardService->extendedStats());
    }

    #[Permission('platform.dashboard.view')]
    public function revenueTrend(Request $request): Response
    {
        $months = (int) $request->param('months', 12);
        return $this->success('', $this->dashboardService->revenueTrend($months));
    }
}
