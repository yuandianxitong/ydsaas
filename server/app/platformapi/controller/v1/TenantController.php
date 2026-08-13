<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\base\Controller;
use core\attribute\Permission;
use app\service\saas\TenantService;
use app\platformapi\validate\v1\TenantValidate;
use think\Response;

/**
 * 平台超管 → 租户 CRUD Controller
 *
 * 五个端点：
 *   GET    /platformapi/tenants       列表（分页 + keyword 搜索）
 *   GET    /platformapi/tenants/:id   详情
 *   POST   /platformapi/tenants       创建（默认 30 天试用）
 *   PUT    /platformapi/tenants/:id   更新（tenant_code 不可改）
 *   DELETE /platformapi/tenants/:id   软删除
 */
class TenantController extends Controller
{
    protected TenantService $tenantService;

    #[Permission('tenant.view')]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->tenantService->paginate($params);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('tenant.view')]
    public function show($id): Response
    {
        return $this->success(lang('messages.get_success'), $this->tenantService->show((int) $id));
    }

    #[Permission('platform.tenant.create')]
    public function store(): Response
    {
        $data = $this->request->param();
        if (empty($data)) {
            $json = json_decode((string) $this->request->getContent(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        // 使用 scene='create'，通过 Controller::validate 第 4 参数传入
        $this->validate((array) $data, TenantValidate::class, [], 'create');
        $tenant = $this->tenantService->create((array) $data);
        return $this->success(lang('messages.create_success'), $tenant);
    }

    #[Permission('platform.tenant.update')]
    public function update($id): Response
    {
        $data = $this->request->param();
        if (empty($data)) {
            $json = json_decode((string) $this->request->getContent(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        $this->validate((array) $data, TenantValidate::class, [], 'update');
        $this->tenantService->update((int) $id, (array) $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * POST /platformapi/tenants/:id/offline-renew
     * body: { plan_id?, months, remark?, transaction_id? }
     */
    #[Permission('platform.tenant.update')]
    public function offlineRenew($id): Response
    {
        $data = $this->request->param();
        if (empty($data)) {
            $json = json_decode((string) $this->request->getContent(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        $this->validate((array) $data, TenantValidate::class, [], 'offlineRenew');
        $result = $this->tenantService->offlineRenew((int) $id, (array) $data);
        return $this->success(lang('messages.update_success'), $result);
    }

    #[Permission('platform.tenant.delete')]
    public function destroy($id): Response
    {
        $this->tenantService->destroy((int) $id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('platform.tenant.reset_password')]
    public function resetPassword($tenant_id): Response
    {
        $data = $this->request->param();
        if (empty($data)) {
            $json = json_decode((string) $this->request->getContent(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        $adminId     = (int) ($data['admin_id'] ?? 0);
        $newPassword = (string) ($data['new_password'] ?? '');
        if ($adminId <= 0) {
            return $this->error(lang('messages.admin_id_required'));
        }
        if (strlen($newPassword) < 6 || strlen($newPassword) > 30) {
            return $this->error(lang('messages.password_length_error'));
        }
        $this->tenantService->resetAdminPassword((int) $tenant_id, $adminId, $newPassword);
        return $this->success(lang('messages.password_reset_success'));
    }

    #[Permission('tenant.view')]
    public function admins($tenant_id): Response
    {
        $list = $this->tenantService->listTenantAdmins((int) $tenant_id);
        return $this->success(lang('messages.get_success'), $list);
    }
}
