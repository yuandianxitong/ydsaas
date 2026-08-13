<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use core\plugin\AppMenuInstaller;
use app\model\saas\Plan;
use app\repository\plugin\PluginGrantRepository;
use app\repository\plugin\TenantPluginRepository;
use app\repository\saas\PlanRepository;
use app\repository\saas\TenantRepository;
use app\repository\system\AdminRepository;

/**
 * 平台超管 → 租户 CRUD Service
 *
 * M1 最小集：
 *   · paginate 支持按 name / tenant_code 关键字搜索
 *   · create 默认 30 天试用（M2 改成按套餐 trial_days 计算）
 *   · update 禁止修改 tenant_code
 *   · destroy 使用 Repository 的 delete（底层自动软删除）
 *
 * M2/M3 会扩展：套餐绑定、试用天数、operator_id 跟踪、审计日志。
 */
class TenantService extends Service
{
    protected TenantRepository $tenantRepository;
    protected AdminRepository $adminRepository;
    protected SubscriptionService $subscriptionService;
    protected TenantInitService $tenantInitService;
    protected SaasOrderService $saasOrderService;
    protected AppMenuInstaller $appMenuInstaller;
    protected PlanRepository $planRepository;
    protected PluginGrantRepository $pluginGrantRepository;
    protected TenantPluginRepository $tenantPluginRepository;

    /**
     * 分页列表
     *
     * @param array{page?:int,limit?:int,page_no?:int,page_size?:int,keyword?:string,status?:int} $params
     */
    public function paginate(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);

        $where = [];
        if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['keyword'])) {
            $kw = (string) $params['keyword'];
            $where[] = ['name|tenant_code', 'like', "%{$kw}%"];
        }

        $result = $this->tenantRepository->paginate($where, $page, $limit);

        // 平台租户列表附加：套餐名称（plan_name）+ 租户端访问域名（access_domain）
        // 之前直接返回 plan_id（数字）+ tenant_code，前端展示意义不大。
        $list = is_array($result['list'] ?? null) ? $result['list'] : [];
        if (!empty($list)) {
            // 批量查 plan name 避免 N+1
            $planIds = array_values(array_unique(array_filter(array_map(
                fn ($r) => (int) ($r['plan_id'] ?? 0),
                $list
            ))));
            $planNames = $this->planRepository->namesByIds($planIds);

            $rootDomains = (array) \think\facade\Config::get('saas.root_domains', []);
            $rootDomain = $rootDomains[0] ?? '';

            foreach ($list as &$row) {
                $pid = (int) ($row['plan_id'] ?? 0);
                $row['plan_name'] = $pid > 0
                    ? ($planNames[$pid] ?? '未知套餐')
                    : '未绑定';
                $row['access_domain'] = ($rootDomain !== '' && !empty($row['tenant_code']))
                    ? $row['tenant_code'] . '.' . $rootDomain
                    : '';
            }
            unset($row);
            $result['list'] = $list;
        }

        return $result;
    }

    /**
     * 详情
     */
    public function show(int $id): array
    {
        $row = $this->tenantRepository->find($id);
        if (!$row) {
            throw new BusinessException('租户不存在');
        }
        return $row;
    }

    /**
     * 创建租户
     *
     * opening_mode:
     *   - trial：试用（createInitial type=1）
     *   - formal：线下正式（不建初始订阅，createAndMarkPaidOffline → extend）
     *   - none：仅建号，无订阅
     *
     * 兼容旧入参：无 opening_mode 时，有 months 且无 trial_days → formal；否则 trial（默认 30 天）。
     */
    public function create(array $data): array
    {
        $code = trim((string) ($data['tenant_code'] ?? ''));
        if ($code === '') {
            throw new BusinessException('tenant_code 不能为空');
        }
        if ($this->tenantRepository->findByCode($code)) {
            throw new BusinessException('tenant_code 已被占用');
        }
        $data['tenant_code'] = $code;

        $mode = $this->resolveOpeningMode($data);
        $planId = (int) ($data['plan_id'] ?? 0);
        $trialDays = isset($data['trial_days']) ? (int) $data['trial_days'] : null;
        $months = isset($data['months']) ? (int) $data['months'] : null;
        $remark = trim((string) ($data['remark'] ?? $data['offline_remark'] ?? ''));
        $transactionId = trim((string) ($data['transaction_id'] ?? ''));

        if ($mode === 'trial') {
            if ($trialDays === null) {
                $trialDays = 30;
            }
            if ($trialDays < 1) {
                throw new BusinessException('试用天数必须 ≥ 1');
            }
            if ($planId <= 0) {
                throw new BusinessException('试用开通必须选择套餐');
            }
        } elseif ($mode === 'formal') {
            if ($months === null || $months < 1) {
                throw new BusinessException('线下正式开通必须填写月数（≥ 1）');
            }
            if ($planId <= 0) {
                throw new BusinessException('线下正式开通必须选择套餐');
            }
        }

        if ($planId > 0) {
            $plan = Plan::find($planId);
            if (!$plan) {
                throw new BusinessException('指定的套餐不存在');
            }
            if ((int) $plan->status !== 1) {
                throw new BusinessException('套餐已禁用');
            }
        }

        // 入库前移除非 tenants 表字段 + 设置默认值
        $tenantData = $data;
        $adminUsername = (string) ($tenantData['admin_username'] ?? '');
        $adminPassword = (string) ($tenantData['admin_password'] ?? '');
        unset(
            $tenantData['trial_days'],
            $tenantData['months'],
            $tenantData['admin_username'],
            $tenantData['admin_password'],
            $tenantData['opening_mode'],
            $tenantData['remark'],
            $tenantData['offline_remark'],
            $tenantData['transaction_id']
        );

        // plan_id 和 expires_at / storage_limit_bytes 会由 subscription / 订单同步
        $tenantData['status']              = isset($tenantData['status']) ? (int) $tenantData['status'] : 1;
        $tenantData['storage_used_bytes']  = 0;
        $tenantData['storage_limit_bytes'] = $tenantData['storage_limit_bytes'] ?? 0;
        $tenantData['plan_id']             = 0;
        unset($tenantData['expires_at']);

        // formal 的 markPaid 自带事务，不能嵌在本事务内（会提前 commit）
        $tenant = $this->runInTransaction(function () use (
            $tenantData,
            $planId,
            $mode,
            $trialDays,
            $adminUsername,
            $adminPassword
        ) {
            $tenant = $this->tenantRepository->create($tenantData);
            $tenantId = (int) $tenant['id'];

            if ($adminUsername !== '' && $adminPassword !== '') {
                $this->tenantInitService->initTenant($tenantId, $adminUsername, $adminPassword);
            }

            if ($mode === 'trial' && $planId > 0 && $trialDays !== null && $trialDays > 0) {
                $this->subscriptionService->createInitial($tenantId, $planId, $trialDays, 1, 0);
            }

            return $this->tenantRepository->find($tenantId) ?? $tenant;
        });

        if ($mode === 'formal' && $planId > 0 && $months !== null && $months > 0) {
            $tenantId = (int) $tenant['id'];
            // 空订阅 → offline 已支付订单 → extend（勿先 createInitial type=2，避免时长翻倍）
            $this->saasOrderService->createAndMarkPaidOffline(
                $tenantId,
                $planId,
                $months,
                2,
                $transactionId,
                $remark !== '' ? $remark : '平台线下正式开通'
            );

            return $this->tenantRepository->find($tenantId) ?? $tenant;
        }

        return $tenant;
    }

    /**
     * 平台线下续费 / 延期：代建 offline 订单并标记已支付。
     *
     * @param array{plan_id?:int,months?:int,remark?:string,transaction_id?:string} $data
     */
    public function offlineRenew(int $id, array $data): array
    {
        $row = $this->tenantRepository->find($id);
        if (!$row) {
            throw new BusinessException('租户不存在');
        }

        $planId = (int) ($data['plan_id'] ?? $row['plan_id'] ?? 0);
        $months = (int) ($data['months'] ?? 0);
        $remark = trim((string) ($data['remark'] ?? ''));
        $transactionId = trim((string) ($data['transaction_id'] ?? ''));

        if ($planId <= 0) {
            throw new BusinessException('必须指定套餐');
        }
        if ($months < 1) {
            throw new BusinessException('续费月数必须 ≥ 1');
        }

        $plan = Plan::find($planId);
        if (!$plan) {
            throw new BusinessException('指定的套餐不存在');
        }
        if ((int) $plan->status !== 1) {
            throw new BusinessException('套餐已禁用');
        }

        $order = $this->saasOrderService->createAndMarkPaidOffline(
            $id,
            $planId,
            $months,
            2,
            $transactionId,
            $remark !== '' ? $remark : '平台线下续费'
        );

        $tenant = $this->tenantRepository->find($id);

        return [
            'tenant' => $tenant,
            'order'  => $order,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function resolveOpeningMode(array $data): string
    {
        $mode = strtolower(trim((string) ($data['opening_mode'] ?? '')));
        if ($mode !== '') {
            if (!in_array($mode, ['trial', 'formal', 'none'], true)) {
                throw new BusinessException('opening_mode 必须是 trial / formal / none');
            }
            return $mode;
        }

        // 兼容旧 API：仅传 months 且未传 trial_days → 正式；否则试用
        if (isset($data['months']) && !isset($data['trial_days'])) {
            return 'formal';
        }

        return 'trial';
    }

    /**
     * 更新租户
     *
     * 注意：tenant_code 是租户永久标识，禁止修改。
     * plan_id 变更时同步 app 插件菜单：旧 plan 多出来的拆掉、新 plan 新增的补上。
     */
    public function update(int $id, array $data): bool
    {
        $row = $this->tenantRepository->find($id);
        if (!$row) {
            throw new BusinessException('租户不存在');
        }
        unset($data['tenant_code']); // 禁止修改

        $oldPlanId = (int) ($row['plan_id'] ?? 0);
        $planChanged = array_key_exists('plan_id', $data) && (int) $data['plan_id'] !== $oldPlanId;
        $newPlanId = $planChanged ? (int) $data['plan_id'] : $oldPlanId;

        $ok = $this->tenantRepository->update($id, $data);
        if (!$ok) {
            return false;
        }

        if ($planChanged) {
            $this->syncAppMenusOnPlanChange($id, $oldPlanId, $newPlanId);
        }

        // 清掉中间件缓存的 tenant_lookup，避免租户端继续看到旧 plan_id / status / expires_at
        \core\tenant\middleware\TenantContextMiddleware::clearTenantCacheById($id);

        return true;
    }

    /**
     * 套餐切换时同步 app 插件菜单。
     *
     * - 旧 plan 授权但新 plan 没有 → 拆（除非租户有 ACTIVE 的购买记录）
     * - 新 plan 授权但旧 plan 没有 → 补
     * - 两个 plan 都授权 → 不动
     * - 只看 kind=app 的插件（plugin 类型走 panels，不在菜单系统里）
     */
    private function syncAppMenusOnPlanChange(int $tenantId, int $oldPlanId, int $newPlanId): void
    {
        $oldPluginIds = $this->appPluginIdsByPlan($oldPlanId);
        $newPluginIds = $this->appPluginIdsByPlan($newPlanId);

        $removed = array_diff($oldPluginIds, $newPluginIds);
        $added   = array_diff($newPluginIds, $oldPluginIds);
        if (empty($removed) && empty($added)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($removed as $pid) {
            if ($this->tenantOwnsViaPurchase($tenantId, (int) $pid, $now)) {
                continue;
            }
            $this->appMenuInstaller->removeForTenant((int) $pid, $tenantId);
        }
        foreach ($added as $pid) {
            $this->appMenuInstaller->copyToTenant((int) $pid, $tenantId);
        }
    }

    /**
     * 某 plan 授权了哪些 kind=app 且 status=ENABLED 的插件。
     *
     * @return array<int, int>
     */
    private function appPluginIdsByPlan(int $planId): array
    {
        return $this->pluginGrantRepository->appPluginIdsByPlan($planId);
    }

    /**
     * 租户是否通过单独购买持有该插件（仍在有效期内、状态 ACTIVE）。
     *
     * v2.6.2 issue #4：JOIN plugins 过滤全局状态，避免下架插件死菜单保留。
     * 与 PluginGrantService::tenantOwnsViaPurchase 同语义同实现。
     */
    private function tenantOwnsViaPurchase(int $tenantId, int $pluginId, string $now): bool
    {
        return $this->tenantPluginRepository->hasActivePurchase($tenantId, $pluginId, $now);
    }

    /**
     * 删除租户（软删除，deleted_at 由模型自动填充）
     */
    public function destroy(int $id): bool
    {
        $row = $this->tenantRepository->find($id);
        if (!$row) {
            throw new BusinessException('租户不存在');
        }
        $ok = $this->tenantRepository->delete($id);
        if ($ok) {
            \core\tenant\middleware\TenantContextMiddleware::clearTenantCacheById($id);
        }
        return $ok;
    }

    /**
     * 重置租户管理员密码（平台超管操作）
     */
    public function resetAdminPassword(int $tenantId, int $adminId, string $newPassword): void
    {
        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant) {
            throw new BusinessException('租户不存在');
        }

        $admin = $this->adminRepository->findByTenant($tenantId, $adminId);

        if (!$admin) {
            throw new BusinessException('管理员不存在或不属于该租户');
        }

        $this->adminRepository->updatePasswordByTenant(
            $tenantId,
            $adminId,
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
    }

    /**
     * 获取租户下的管理员列表（平台超管查看）
     */
    public function listTenantAdmins(int $tenantId): array
    {
        $tenant = $this->tenantRepository->find($tenantId);
        if (!$tenant) {
            throw new BusinessException('租户不存在');
        }

        return $this->adminRepository->listByTenant($tenantId);
    }
}
