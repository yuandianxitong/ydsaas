<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use app\service\system\PermissionService;
use app\tenantapi\validate\v1\system\PermissionValidate;
use core\attribute\Permission as Perm;
use core\attribute\PermissionSkip;
use core\base\Controller;
use think\Response;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    #[Perm('platform.permission.list')]
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

    #[Perm('platform.permission.create')]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, PermissionValidate::class, [], 'create');

        $result = $this->permissionService->createPermission($data);

        return $this->success(lang('messages.create_success'), $result);
    }

    #[Perm('platform.permission.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, PermissionValidate::class, [], 'update');

        $this->permissionService->updatePermission($id, $data);

        return $this->success(lang('messages.update_success'));
    }

    #[Perm('platform.permission.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->permissionService->deletePermission($id);

        return $this->success(lang('messages.delete_success'));
    }

    #[Perm('platform.permission.create')]
    public function batchCreate(): Response
    {
        $permissions = $this->request->post('permissions', []);

        if (empty($permissions)) {
            return $this->error(lang('business.permission_data_required'));
        }

        $this->permissionService->batchCreatePermissions($permissions);

        return $this->success(lang('messages.batch_create_success'));
    }
}
