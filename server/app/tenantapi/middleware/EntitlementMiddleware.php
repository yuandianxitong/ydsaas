<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\middleware;

use app\service\saas\EntitlementService;
use Closure;
use core\auth\Permission as PermissionChecker;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\facade\App;
use think\Request;
use think\Response;

/**
 * 在 plugin tenantapi 路由组前做权益校验：
 *   - 未登录 / 平台 host → 401
 *   - 超管 bypass
 *   - 租户权益不包含 $code → 403
 */
class EntitlementMiddleware
{
    protected EntitlementService $entitlementService;
    protected PermissionChecker $permission;

    public function __construct()
    {
        $this->entitlementService = App::make(EntitlementService::class);
        $this->permission = App::make(PermissionChecker::class);
    }

    /**
     * @param string $code 权益码，由 ThinkPHP 路由中间件元组语法注入。
     *                     正确：Route::middleware([['entitlement', ['mall']]])
     *                     错误：Route::middleware(['entitlement:mall']) — route 级不解析 ":"
     */
    public function handle(Request $request, Closure $next, string $code): Response
    {
        $tenant = TenantContext::current();
        if ($tenant === null || $tenant->isPlatform()) {
            throw new BusinessException(lang('auth.entitlement_unauthorized'), 401);
        }

        $userId = (int) ($request->userId ?? 0);
        if ($userId > 0 && $this->permission->isSuperAdmin($userId)) {
            return $next($request);
        }

        // userId=0 (C-side visitor on /api routes) is fine here — no auth required;
        // entitlement check below is the only gate.
        if (!$this->entitlementService->has($tenant->id(), $code)) {
            throw new BusinessException(
                lang('auth.entitlement_not_granted', ['code' => $code]),
                403
            );
        }

        return $next($request);
    }
}
