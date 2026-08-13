<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\controller\v1\system;

use app\service\system\PermissionService;
use core\base\Controller;
use think\Response;
use core\attribute\PermissionSkip;
use core\attribute\Permission;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    #[Permission('system.permission.list')]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->permissionService->getPermissionList($params);

        if (!empty($params['grouped'])) {
            return $this->success(lang('messages.get_success'), $result);
        }

        return $this->paginate($result);
    }

    #[PermissionSkip]
    public function tree(): Response
    {
        $result = $this->permissionService->getPermissionTree();
        return $this->success(lang('messages.get_success'), $result);
    }
}
