<?php

declare(strict_types=1);

namespace app\api\controller\v1\pc;

use app\service\saas\TenantPcConfigService;
use app\service\diy\DiyPageService;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\Response;

class PcConfigController extends Controller
{
    protected TenantPcConfigService $configService;
    protected DiyPageService $diyPageService;

    public function get(): Response
    {
        $ctx = TenantContext::current();
        if ($ctx === null || $ctx->isPlatform()) {
            throw new BusinessException('租户上下文缺失', 401);
        }

        $cfg = $this->configService->get($ctx->id());
        $public = array_intersect_key($cfg, array_flip([
            'site_name', 'site_logo', 'site_intro', 'theme_color',
            'home_type', 'home_app_code', 'home_page', 'nav',
            'seo', 'login_enabled', 'register_enabled', 'status', 'fallback',
        ]));

        return $this->success('ok', $public);
    }

    public function diyPage(): Response
    {
        $ctx = TenantContext::current();
        if ($ctx === null || $ctx->isPlatform()) {
            throw new BusinessException('租户上下文缺失', 401);
        }
        $key = (string) $this->request->param('key', 'home');
        $page = $this->diyPageService->getPublishedForTenant($ctx->id(), $key, 'pc');
        if ($page === null) {
            throw new BusinessException('页面不存在', 404);
        }
        return $this->success('ok', $page);
    }
}
