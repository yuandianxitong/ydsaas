<?php

declare(strict_types=1);

namespace app\platformapi\controller\v1\marketplace;

use app\service\marketplace\MarketplaceChangelogService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class ChangelogController extends Controller
{
    protected MarketplaceChangelogService $svc;

    #[Permission('marketplace.catalog.view')]
    public function show($entitlementCode): Response
    {
        $toVersion = (string) $this->request->get('to_version', '');
        if ($toVersion === '') {
            throw new BusinessException('to_version 参数必填', 422);
        }
        $changelog = $this->svc->fetch((string) $entitlementCode, $toVersion);
        return $this->success('ok', ['changelog' => $changelog]);
    }
}
