<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\platformapi\controller\v1;

use app\service\plugin\PluginGrantService;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

class PluginGrantController extends Controller
{
    protected PluginGrantService $grantService;

    #[Permission('platform.plugin_grant.list')]
    public function listByPlan($planId): Response
    {
        return $this->success(lang('messages.get_success'), $this->grantService->listByPlan((int) $planId));
    }

    #[Permission('platform.plugin_grant.sync')]
    public function syncByPlan($planId): Response
    {
        $pluginIds     = (array) $this->request->post('plugin_ids', []);
        $autoEnableIds = (array) $this->request->post('auto_enable_ids', []);
        $diff = $this->grantService->sync((int) $planId, $pluginIds, $autoEnableIds);
        return $this->success(lang('messages.update_success'), $diff);
    }
}
