<?php

declare(strict_types=1);

namespace app\platformapi\controller\v1\marketplace;

use app\service\marketplace\MarketplaceInstallIntentService;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

class InstallIntentController extends Controller
{
    protected MarketplaceInstallIntentService $intentService;

    /**
     * 创建安装意图。
     *   body: { app_code?: string, callback_url?: string }
     *   - app_code 缺省表示纯连接意图（连接账号 / 更换账号）
     *   - 未连接时需 callback_url 以发起 OAuth
     *
     * 返回 status: authorization_required | connected | ready | purchase_required | incompatible
     */
    #[Permission('marketplace.connection.manage')]
    public function create(): Response
    {
        $appCode  = trim((string) $this->request->post('app_code', ''));
        $callback = (string) $this->request->post('callback_url', '');

        // 未连接时 initiate() 会校验 callback_url；已连接时不需要 callback。
        $result = $this->intentService->create($appCode !== '' ? $appCode : null, $callback);

        return $this->success('ok', $result);
    }
}
