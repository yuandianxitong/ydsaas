<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\api\controller\v1\saas;

use core\base\Controller;
use core\saas\payment\SaasPaymentGateway;
use app\service\saas\SaasOrderService;
use think\Response;
use think\facade\Log;

/**
 * SaaS 支付回调（匿名路由，通过签名校验身份）
 *
 * 请求来自微信 / 支付宝平台服务器，不带 JWT。
 * 所有异常必须 try/catch，失败也要返回非 success 响应让平台重试。
 * 幂等性由 SaasOrderService::handleCallback 内部保证。
 */
class SaasPayNotifyController extends Controller
{
    protected SaasOrderService $saasOrderService;

    /**
     * POST /api/saas/notify/wechat
     *
     * WeChat Pay v3 回调：JSON body + header 签名。
     * 这里把 post / param / headers / raw body 全部打包成一个
     * 扁平数组交给 driver 的 verifyNotify 处理 —— 具体怎么验签是
     * driver 的事。
     */
    public function wechatNotify(): Response
    {
        $params = [];
        try {
            $params = array_merge(
                $this->request->post(),
                $this->request->param(),
                ['headers' => $this->request->header()],
                ['raw' => $this->request->getContent()]
            );

            // 先拿 driver，配置错误 fail-fast，不会跑到 handleCallback
            $driver = SaasPaymentGateway::channel('wechat');

            $this->saasOrderService->handleCallback('wechat', $params);

            return Response::create($driver->successResponse(), 'html', 200);
        } catch (\Throwable $e) {
            Log::error('[SaaS wechat notify] ' . $e->getMessage(), [
                'exception'    => get_class($e),
                'out_trade_no' => (string) ($params['out_trade_no'] ?? ''),
                'trace'        => $e->getTraceAsString(),
            ]);
            // 传 array 让 ThinkPHP 只 json_encode 一次；不要先 json_encode 再传 'json'
            return Response::create([
                'code'    => 'FAIL',
                'message' => '处理失败',
            ], 'json', 500);
        }
    }

    /**
     * POST /api/saas/notify/alipay
     *
     * Alipay 回调：form-encoded POST，参数在 post() 里。
     */
    public function alipayNotify(): Response
    {
        $params = [];
        try {
            $params = $this->request->post();

            $driver = SaasPaymentGateway::channel('alipay');

            $this->saasOrderService->handleCallback('alipay', $params);

            return Response::create($driver->successResponse(), 'html', 200);
        } catch (\Throwable $e) {
            Log::error('[SaaS alipay notify] ' . $e->getMessage(), [
                'exception'    => get_class($e),
                'out_trade_no' => (string) ($params['out_trade_no'] ?? ''),
                'trace'        => $e->getTraceAsString(),
            ]);
            return Response::create('fail', 'html', 500);
        }
    }
}
