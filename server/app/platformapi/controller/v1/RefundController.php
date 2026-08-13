<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\base\Controller;
use core\attribute\Permission;
use app\service\payment\RefundService;
use think\Request;
use think\Response;

class RefundController extends Controller
{
    protected RefundService $refundService;

    #[Permission('platform.refund.list')]
    public function index(Request $request): Response
    {
        $params = $request->param();
        $params['order_type'] = 'saas';
        return $this->success('', $this->refundService->getRefundList($params));
    }

    #[Permission('platform.refund.list')]
    public function show(int $id): Response
    {
        $detail = $this->refundService->getRefundDetail($id);
        return $detail ? $this->success('', $detail) : $this->error(lang('messages.refund_not_found'));
    }

    #[Permission('platform.refund.create')]
    public function refund(Request $request, int $orderId): Response
    {
        $amount  = (int) $request->param('amount');
        $reason  = (string) $request->param('reason', '');
        $adminId = $request->platformAdminId;
        $result  = $this->refundService->refundSaasOrder($orderId, $amount, $reason, $adminId);
        return $this->success('', $result);
    }
}
