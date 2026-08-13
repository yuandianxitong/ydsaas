<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\platformapi\controller\v1;

use app\repository\saas\TenantMobileBuildRepository;
use app\service\saas\TenantMobileBuildService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

/**
 * 平台超管：移动端构建全局监控。
 *
 *   GET  /platformapi/mobile-builds                 全部租户构建列表（分页 + 过滤 status/platform/tenant_id）
 *   POST /platformapi/mobile-builds/force-fail      把所有 running 超时的强制收尾
 */
class MobileBuildController extends Controller
{
    protected TenantMobileBuildRepository $buildRepo;
    protected TenantMobileBuildService $buildService;

    #[Permission('platform.mobile.build.view')]
    public function index(): Response
    {
        $page  = (int) $this->request->param('page', 1);
        $limit = max(1, min((int) $this->request->param('limit', 20), 100));

        $q = $this->buildRepo->queryGlobal()->order('id', 'desc');
        if ($status = $this->request->param('status')) {
            $q = $q->where('status', (int) $status);
        }
        if ($platform = $this->request->param('platform')) {
            $q = $q->where('platform', (string) $platform);
        }
        if ($tenantId = $this->request->param('tenant_id')) {
            $q = $q->where('tenant_id', (int) $tenantId);
        }

        $total = (int) (clone $q)->count();
        $items = $q->page($page, $limit)->select()->toArray();
        // 列表页不需要大 JSON 字段
        foreach ($items as &$row) {
            unset($row['included_plugins_json'], $row['pages_json'], $row['manifest_json'], $row['runtime_json']);
        }
        unset($row);

        return $this->success('ok', [
            'list'       => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[Permission('platform.mobile.build.manage')]
    public function forceFailStuck(): Response
    {
        $threshold = (int) ($this->request->post('threshold_seconds', 1800));
        if ($threshold < 60) {
            throw new BusinessException('threshold_seconds 不可小于 60', 422);
        }
        $count = $this->buildService->forceFailStuckRunning($threshold);
        return $this->success('ok', ['closed' => $count]);
    }
}
