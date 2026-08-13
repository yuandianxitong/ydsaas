<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\controller\v1\subscription;

use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use core\attribute\PermissionSkip;
use app\service\saas\SubscriptionService;
use app\service\saas\SaasOrderService;
use think\Response;

/**
 * 租户订阅 + 支付端点（M3B）
 *
 * 所有方法都在 tenantapi，需要 tenant 登录。租户只能操作自己的订阅/订单 ——
 * pay / queryOrder 显式校验 order.tenant_id === ctx.tenantId（红线）。
 *
 * 所有方法都标注 #[PermissionSkip]：订阅/付费是租户级通用操作，不走
 * 权限点控制（任何已登录管理员都能查看和续费自己的套餐）。
 */
class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;
    protected SaasOrderService $saasOrderService;

    /**
     * 取当前租户 ID（来自 TenantContextMiddleware 注入的 TenantContext）。
     * 没有上下文意味着 TenantAuth 中间件出问题了，直接抛错。
     */
    private function tenantId(): int
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            throw new BusinessException('租户上下文缺失');
        }
        return $ctx->id();
    }

    /**
     * GET /tenantapi/subscription/current
     */
    #[PermissionSkip]
    public function current(): Response
    {
        $tenantId = $this->tenantId();
        $sub = $this->subscriptionService->getCurrent($tenantId);
        return $this->success(lang('messages.get_success'), ['subscription' => $sub]);
    }

    /**
     * POST /tenantapi/subscription/create-order
     * body: { plan_id, months, channel?, method? }
     */
    #[PermissionSkip]
    public function createOrder(): Response
    {
        $tenantId = $this->tenantId();
        $planId = (int) $this->request->param('plan_id', 0);
        $months = (int) $this->request->param('months', 1);
        $channel = (string) $this->request->param('channel', 'wechat');
        $method = (string) $this->request->param('method', 'native');

        if ($planId <= 0) {
            throw new BusinessException('参数错误：plan_id 必填');
        }
        if ($months <= 0) {
            throw new BusinessException('参数错误：months 必须 > 0');
        }
        if (!in_array($channel, ['wechat', 'alipay'], true)) {
            throw new BusinessException('参数错误：channel 必须是 wechat 或 alipay');
        }
        if (!in_array($method, ['native', 'jsapi', 'h5', 'app', 'page', 'wap'], true)) {
            throw new BusinessException('参数错误：method 不支持');
        }

        $order = $this->saasOrderService->createOrder(
            $tenantId,
            $planId,
            $months,
            2,
            $channel,
            $method
        );

        return $this->success('下单成功', $order);
    }

    /**
     * POST /tenantapi/subscription/pay
     * body: { order_id }
     */
    #[PermissionSkip]
    public function pay(): Response
    {
        $tenantId = $this->tenantId();
        $orderId = (int) $this->request->param('order_id', 0);
        if ($orderId <= 0) {
            throw new BusinessException('参数错误：order_id 必填');
        }

        $result = $this->saasOrderService->createPayment($orderId, $tenantId);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * GET /tenantapi/subscription/query-order?order_id=xxx
     */
    #[PermissionSkip]
    public function queryOrder(): Response
    {
        $tenantId = $this->tenantId();
        $orderId = (int) $this->request->param('order_id', 0);
        if ($orderId <= 0) {
            throw new BusinessException('参数错误：order_id 必填');
        }

        $order = $this->saasOrderService->show($orderId, $tenantId);
        return $this->success(lang('messages.get_success'), [
            'order' => $this->projectOrder($order),
        ]);
    }

    /**
     * GET /tenantapi/subscription/pending-renewal
     */
    #[PermissionSkip]
    public function pendingRenewal(): Response
    {
        $tenantId = $this->tenantId();
        $order = $this->saasOrderService->pendingRenewalOfTenant($tenantId);

        return $this->success(
            lang('messages.get_success'),
            $order ? $this->projectOrder($order) : null
        );
    }

    /**
     * GET /tenantapi/subscription/plans
     *
     * 返回所有 status=1 (上架中) 的套餐列表，给续费页的套餐选择器用。
     * 不分页 —— plans 表行数很少，前端一次拉完。
     */
    #[PermissionSkip]
    public function plans(): Response
    {
        $rows = $this->subscriptionService->getAvailablePlans();
        return $this->success(lang('messages.get_success'), ['list' => $rows]);
    }

    /**
     * 把订单内部字段过滤掉，只暴露给租户客户端必要的字段。
     * 隐藏 prepay_id / transaction_id / payment_channel / payment_method
     * 等支付内部状态。
     */
    private function projectOrder(array $order): array
    {
        return [
            'id'         => $order['id'] ?? null,
            'order_no'   => $order['order_no'] ?? '',
            'plan_id'    => (int) ($order['plan_id'] ?? 0),
            'months'     => (int) ($order['months'] ?? 0),
            'amount'     => $order['amount'] ?? '0.00',
            'status'     => (int) ($order['status'] ?? 0),
            'created_at' => $order['create_time'] ?? $order['created_at'] ?? null,
            'paid_at'    => $order['paid_at'] ?? null,
            'expired_at' => $order['expired_at'] ?? null,
        ];
    }
}
