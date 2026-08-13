<?php

declare(strict_types=1);

namespace app\tenantapi\controller\v1\pc;

use app\service\saas\TenantPcConfigService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\Response;

class PcConfigController extends Controller
{
    protected TenantPcConfigService $configService;

    private function tenantId(): int
    {
        $ctx = TenantContext::current();
        if ($ctx === null || $ctx->isPlatform()) {
            throw new BusinessException('租户上下文缺失');
        }
        return $ctx->id();
    }

    #[Permission('pc.config.view')]
    public function get(): Response
    {
        return $this->success('ok', $this->configService->get($this->tenantId()));
    }

    #[Permission('pc.config.update')]
    public function update(): Response
    {
        return $this->success('updated', $this->configService->save($this->tenantId(), $this->request->put()));
    }

    #[Permission('pc.config.view')]
    public function options(): Response
    {
        return $this->success('ok', $this->configService->options($this->tenantId()));
    }
}
