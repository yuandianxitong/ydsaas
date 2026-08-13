<?php
declare(strict_types=1);

namespace app\tenantapi\controller\v1\payment;

use core\base\Controller;
use core\attribute\Permission;
use core\tenant\TenantContext;
use app\service\payment\RefundService;
use think\Request;
use think\Response;

class RefundController extends Controller
{
    protected RefundService $refundService;

    #[Permission('payment.refund.list')]
    public function index(Request $request): Response
    {
        $params               = $request->param();
        $params['order_type'] = 'business';
        $params['tenant_id']  = TenantContext::current()->id();
        return $this->success('', $this->refundService->getRefundList($params));
    }

    #[Permission('payment.refund.list')]
    public function show(int $id): Response
    {
        $detail = $this->refundService->getRefundDetail($id);
        return $detail ? $this->success('', $detail) : $this->error('退款记录不存在');
    }

    #[Permission('payment.refund.create')]
    public function refund(Request $request, int $orderId): Response
    {
        $amount   = (int) $request->param('amount');
        $reason   = (string) $request->param('reason', '');
        $adminId  = $request->userId ?? 0;
        $tenantId = TenantContext::current()->id();
        $result   = $this->refundService->refundBusinessOrder($orderId, $amount, $reason, $adminId, $tenantId);
        return $this->success('', $result);
    }
}
