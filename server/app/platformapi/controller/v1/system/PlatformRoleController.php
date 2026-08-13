<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\saas\PlatformRoleService;
use app\service\saas\PlatformMenuService;
use think\Request;
use think\Response;

class PlatformRoleController extends Controller
{
    protected PlatformRoleService $platformRoleService;
    protected PlatformMenuService $platformMenuService;

    #[Permission('platform.role.list')]
    public function index(Request $request): Response
    {
        return $this->success('', $this->platformRoleService->getRoleList($request->param()));
    }

    #[Permission('platform.role.list')]
    public function show(int $id): Response
    {
        return $this->success('', $this->platformRoleService->getRolePermissions($id));
    }

    #[Permission('platform.role.create')]
    public function store(Request $request): Response
    {
        return $this->success('', $this->platformRoleService->createRole($request->param()));
    }

    #[Permission('platform.role.update')]
    public function update(Request $request, int $id): Response
    {
        $this->platformRoleService->updateRole($id, $request->param());
        return $this->success();
    }

    #[Permission('platform.role.delete')]
    public function delete(int $id): Response
    {
        $this->platformRoleService->deleteRole($id);
        return $this->success();
    }

    #[Permission('platform.role.permission')]
    public function assignPermissions(Request $request, int $id): Response
    {
        $menuIds = $request->param('menu_ids', []);
        $this->platformRoleService->assignPermissions($id, $menuIds);
        return $this->success();
    }

    #[Permission('platform.role.status')]
    public function updateStatus(Request $request, int $id): Response
    {
        $this->platformRoleService->updateStatus($id, (int) $request->param('status'));
        return $this->success();
    }

    #[PermissionSkip]
    public function menuTree(): Response
    {
        return $this->success('', $this->platformMenuService->getMenuTree());
    }

    #[PermissionSkip]
    public function options(): Response
    {
        return $this->success('', $this->platformRoleService->getAllRoleOptions());
    }
}
