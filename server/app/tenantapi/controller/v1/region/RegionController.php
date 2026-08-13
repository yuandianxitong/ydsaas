<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\controller\v1\region;

use app\service\region\RegionService;
use core\attribute\PermissionSkip;
use core\base\Controller;
use think\Response;

/**
 * 租户端区域只读接口。CRUD 已迁到 platformapi；这里仅保留 tree() 给前端 cascader 使用。
 */
class RegionController extends Controller
{
    protected RegionService $regionService;

    #[PermissionSkip]
    public function tree(): Response
    {
        return $this->success(lang('messages.get_success'), $this->regionService->getTree());
    }
}
