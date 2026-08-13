<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\controller\v1\plugin;

use app\repository\plugin\PluginRepository;
use app\service\plugin\TenantPluginConfigService;
use app\service\plugin\TenantPluginService;
use app\service\saas\EntitlementService;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\Response;

class PluginController extends Controller
{
    protected TenantPluginService $service;
    protected TenantPluginConfigService $configService;
    protected EntitlementService $entitlementService;
    protected PluginRepository $pluginRepository;

    // 只读「本租户可用插件」列表：全局侧栏（sub-sidebar）每页 onMounted 调用以渲染插件贡献的
    // 菜单区块，属 UI chrome，任何已认证的租户管理员都需要。不能要求 plugin.list（插件管理权限），
    // 否则未授权插件管理的普通角色每页 403。管理动作（enable/disable/config/purchase）仍各自受权限约束。
    #[PermissionSkip]
    public function index(): Response
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            throw new BusinessException('租户上下文缺失', 500);
        }
        $tenantId = $ctx->id();
        $planId = (int) ($ctx->tenantData()['plan_id'] ?? 0);
        return $this->success(lang('messages.get_success'), $this->service->listAvailable($tenantId, $planId));
    }

    #[Permission('plugin.enable')]
    public function enable($id): Response
    {
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        return $this->success(lang('messages.update_success'), $this->service->enable($tenantId, (int) $id));
    }

    #[Permission('plugin.disable')]
    public function disable($id): Response
    {
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        return $this->success(lang('messages.update_success'), $this->service->disable($tenantId, (int) $id));
    }

    #[Permission('plugin.config.get')]
    public function getConfig($pluginCode): Response
    {
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        return $this->success(lang('messages.get_success'), $this->configService->getConfig($tenantId, (string) $pluginCode));
    }

    #[Permission('plugin.config.update')]
    public function updateConfig($pluginCode): Response
    {
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        $kv = (array) $this->request->post('config', []);
        return $this->success(lang('messages.update_success'), $this->configService->updateConfig($tenantId, (string) $pluginCode, $kv));
    }

    #[Permission('plugin.list')]
    public function configSchema($code): Response
    {
        // v2.6.4 issue #3：URL :code 始终是 plugin code（与磁盘 plugins/{code}/
        // 目录命名一致），不是 entitlement。先 findByCode 拿到插件行，再用
        // row.entitlement 走 EntitlementService（entitlement 可与 code 不同）。
        // 这样在 PluginPackageService 等约定下保持一致。
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        if ($tenantId <= 0) {
            throw new BusinessException('租户上下文缺失', 500);
        }
        $plugin = $this->pluginRepository->findByCode((string) $code);
        if (!$plugin) {
            throw new BusinessException("插件不存在: {$code}", 404);
        }
        $entitlement = (string) ($plugin['entitlement'] ?? $plugin['code'] ?? '');
        if (!$this->entitlementService->has($tenantId, $entitlement)) {
            throw new BusinessException('当前无该插件权益，无法读取其配置 schema', 403);
        }
        $pluginsDir = \think\facade\App::getRootPath() . 'plugins';
        $file = $pluginsDir . '/' . $code . '/Config/schema.json';
        $schema = file_exists($file) ? json_decode((string) file_get_contents($file), true) : null;
        return $this->success(lang('messages.get_success'), $schema);
    }

    #[Permission('plugin.order.list')]
    public function orders(): Response
    {
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        $page  = max(1, (int) $this->request->get('page', 1));
        $limit = min(100, max(1, (int) $this->request->get('limit', 20)));
        $status = $this->request->get('status');
        $filters = [
            'keyword' => (string) $this->request->get('keyword', ''),
            'status'  => ($status === '' || $status === null) ? null : (int) $status,
        ];

        $orderService = app(\app\service\saas\SaasOrderService::class);
        $data = $orderService->listPluginOrders($tenantId, $page, $limit, $filters);

        return $this->success('查询成功', $data);
    }

    #[Permission('plugin.purchase')]
    public function purchase($id): Response
    {
        $tenantId = (int) (TenantContext::current()?->id() ?? 0);
        $months   = (int) $this->request->post('months', 1);
        $amount   = (float) $this->request->post('amount', 0);
        $channel  = (string) $this->request->post('channel', 'wechat');
        $method   = (string) $this->request->post('method', 'native');

        if ($months <= 0) {
            throw new BusinessException('购买月数必须 > 0', 422);
        }
        if ($amount <= 0) {
            throw new BusinessException('订单金额必须 > 0', 422);
        }

        $orderService = app(\app\service\saas\SaasOrderService::class);
        $order = $orderService->createPluginOrder($tenantId, (int) $id, $months, $amount, $channel, $method);
        // 创建支付参数
        $payment = $orderService->createPayment((int) ($order['id'] ?? 0), $tenantId);

        return $this->success(lang('messages.create_success'), [
            'order'   => $order,
            'payment' => $payment,
        ]);
    }

    #[Permission('plugin.testdata')]
    public function testdata($id): Response
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            throw new BusinessException('租户上下文缺失', 500);
        }
        return $this->success(
            lang('messages.update_success'),
            $this->service->importTestdata($ctx->id(), (int) $id)
        );
    }
}
