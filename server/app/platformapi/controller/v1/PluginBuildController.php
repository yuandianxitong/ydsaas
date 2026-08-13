<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\platformapi\controller\v1;

use app\model\plugin\PluginBuild;
use app\repository\plugin\PluginBuildRepository;
use app\service\plugin\PluginBuildService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class PluginBuildController extends Controller
{
    protected PluginBuildRepository $buildRepo;
    protected PluginBuildService $buildService;

    #[Permission('platform.plugin_build.list')]
    public function index(): Response
    {
        $page  = (int) $this->request->param('page', 1);
        $limit = (int) $this->request->param('limit', 20);
        $result = $this->buildRepo->listPaginated($page, $limit);

        return $this->success(lang('messages.get_success'), [
            'list'       => $result['list'],
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $result['total'],
                'last_page'    => max(1, (int) ceil($result['total'] / max(1, $limit))),
            ],
        ]);
    }

    #[Permission('platform.plugin_build.detail')]
    public function show($id): Response
    {
        $row = $this->buildRepo->find((int) $id);
        if (!$row) {
            throw new BusinessException('构建任务不存在', 404);
        }

        return $this->success(lang('messages.get_success'), $row);
    }

    #[Permission('platform.plugin_build.rebuild')]
    public function rebuild(): Response
    {
        $target = (string) $this->request->post('target', '');
        if (!in_array($target, [PluginBuild::TARGET_PLATFORM, PluginBuild::TARGET_TENANT], true)) {
            throw new BusinessException('target 必须是 platform 或 tenant', 422);
        }

        $operatorId = (int) ($this->request->platformAdminId ?? 0) ?: null;
        $buildId    = $this->buildService->enqueue($target, 'manual', null, $operatorId);

        return $this->success(lang('messages.create_success'), ['build_id' => $buildId]);
    }
}
