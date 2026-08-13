<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\controller\v1\mobile;

use app\service\saas\MobileEligibilityService;
use app\service\saas\TenantMobileConfigService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\Response;

/**
 * 租户后台：移动端配置 CRUD（单行一对一）。
 *
 * GET  /tenantapi/mobile/config  → 当前租户配置
 * PUT  /tenantapi/mobile/config  → 保存
 */
class MobileConfigController extends Controller
{
    protected TenantMobileConfigService $configService;
    protected MobileEligibilityService $eligibilityService;

    private function tenantId(): int
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            throw new BusinessException('租户上下文缺失');
        }
        return $ctx->id();
    }

    #[Permission('mobile.config.view')]
    public function get(): Response
    {
        return $this->success('ok', $this->configService->get($this->tenantId()));
    }

    #[Permission('mobile.config.update')]
    public function update(): Response
    {
        $payload = $this->request->put();
        $saved   = $this->configService->save($this->tenantId(), $payload);
        return $this->success('updated', $saved);
    }

    /**
     * GET /tenantapi/mobile/config/eligible
     * 返回当前租户可作为首页 / tabBar 的插件清单（前端下拉来源）。
     */
    #[Permission('mobile.config.view')]
    public function eligible(): Response
    {
        return $this->success('ok', $this->eligibilityService->listEligible($this->tenantId()));
    }
}
