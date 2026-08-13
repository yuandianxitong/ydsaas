<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\saas\PlatformAdminService;
use app\service\saas\PlatformRoleService;
use think\Request;
use think\Response;

class PlatformAdminController extends Controller
{
    protected PlatformAdminService $platformAdminService;
    protected PlatformRoleService $platformRoleService;

    #[Permission('platform.admin.list')]
    public function index(Request $request): Response
    {
        return $this->success('', $this->platformAdminService->getAdminList($request->param()));
    }

    #[Permission('platform.admin.list')]
    public function show(int $id): Response
    {
        $admin = $this->platformAdminService->getAdminDetail($id);
        return $admin ? $this->success('', $admin) : $this->error(lang('auth.admin_not_found'));
    }

    #[Permission('platform.admin.create')]
    public function store(Request $request): Response
    {
        return $this->success('', $this->platformAdminService->createAdmin($request->param()));
    }

    #[Permission('platform.admin.update')]
    public function update(Request $request, int $id): Response
    {
        $this->platformAdminService->updateAdmin($id, $request->param());
        return $this->success();
    }

    #[Permission('platform.admin.delete')]
    public function delete(Request $request, int $id): Response
    {
        $this->platformAdminService->deleteAdmin($id, $request->platformAdminId);
        return $this->success();
    }

    #[Permission('platform.admin.status')]
    public function updateStatus(Request $request, int $id): Response
    {
        $this->platformAdminService->updateStatus($id, (int) $request->param('status'));
        return $this->success();
    }

    #[Permission('platform.admin.update')]
    public function resetPassword(Request $request, int $id): Response
    {
        $this->platformAdminService->resetPassword($id, $request->param('password'));
        return $this->success();
    }

    #[PermissionSkip]
    public function changePassword(Request $request): Response
    {
        $this->platformAdminService->changePassword(
            $request->platformAdminId,
            $request->param('old_password'),
            $request->param('new_password')
        );
        return $this->success();
    }

    #[PermissionSkip]
    public function roleOptions(): Response
    {
        return $this->success('', $this->platformRoleService->getAllRoleOptions());
    }
}
