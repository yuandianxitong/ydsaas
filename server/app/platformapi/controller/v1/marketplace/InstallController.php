<?php

declare(strict_types=1);

namespace app\platformapi\controller\v1\marketplace;

use app\service\marketplace\MarketplaceInstallService;
use app\service\marketplace\MarketplaceUpgradeService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class InstallController extends Controller
{
    protected MarketplaceInstallService $installSvc;
    protected MarketplaceUpgradeService $upgradeSvc;

    #[Permission('marketplace.install')]
    public function install(): Response
    {
        $code = (string) $this->request->post('entitlement_code', '');
        $ver  = (string) $this->request->post('version_id', '') ?: null;
        if ($code === '') {
            throw new BusinessException('entitlement_code 必填', 422);
        }
        return $this->success('ok', $this->installSvc->install($code, $ver));
    }

    #[Permission('marketplace.install')]
    public function upgrade(): Response
    {
        $id = (int) $this->request->post('plugin_id', 0);
        if ($id <= 0) {
            throw new BusinessException('plugin_id 必填', 422);
        }
        return $this->success('ok', $this->upgradeSvc->upgrade($id));
    }
}
