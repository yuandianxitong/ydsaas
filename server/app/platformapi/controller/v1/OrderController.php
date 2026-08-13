<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\base\Controller;
use core\attribute\Permission;
use app\service\saas\SaasOrderService;
use think\Response;

/**
 * 平台超管 → SaaS 订单管理 Controller（M3B T91）
 *
 * 端点：
 *   GET  /platformapi/orders                列表（分页 + tenant_id / status / order_no 过滤）
 *   GET  /platformapi/orders/:id            详情
 *   POST /platformapi/orders/:id/mark-paid  手动标记已支付（线下汇款场景 / 测试用）
 *   POST /platformapi/orders/:id/cancel     取消订单
 *   POST /platformapi/orders/:id/refund     退款（仅状态机推进，不实际调用 gateway.refund）
 *
 * refund 在 M3B 阶段**不**调用支付网关的退款接口，只做 status 推进。
 * 实际退款的 gateway 调用留给 M3C 或之后做。
 */
class OrderController extends Controller
{
    protected SaasOrderService $saasOrderService;

    /**
     * GET /platformapi/orders
     */
    #[Permission('order.view')]
    public function index(): Response
    {
        $params = $this->getRequestData();
        $result = $this->saasOrderService->paginate($params);
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * GET /platformapi/orders/:id
     */
    #[Permission('order.view')]
    public function show($id): Response
    {
        return $this->success(lang('messages.get_success'), $this->saasOrderService->show((int) $id));
    }

    /**
     * POST /platformapi/orders/:id/mark-paid
     * body: { transaction_id? }
     */
    #[Permission('order.view')]
    public function markPaid($id): Response
    {
        $txId = (string) $this->request->param('transaction_id', 'manual');
        $order = $this->saasOrderService->markPaid((int) $id, $txId);
        return $this->success(lang('messages.marked_paid'), $order);
    }

    /**
     * POST /platformapi/orders/:id/cancel
     */
    #[Permission('order.view')]
    public function cancel($id): Response
    {
        $this->saasOrderService->markCancelled((int) $id);
        return $this->success(lang('messages.marked_cancelled'));
    }
}
