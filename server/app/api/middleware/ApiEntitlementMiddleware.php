<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\api\middleware;

use app\service\saas\EntitlementService;
use Closure;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\facade\App;
use think\Request;
use think\Response;

/**
 * C 端 /api 路由的权益校验：
 *   - 无 TenantContext / 平台 host → 401
 *   - 租户权益不包含 $code → 403
 *
 * 与 tenantapi 的 EntitlementMiddleware 区别：
 *   - 不依赖 PermissionChecker（C 端没有超管概念）
 *   - 匿名访客也走相同权益门，不存在管理员绕行
 */
class ApiEntitlementMiddleware
{
    protected EntitlementService $entitlementService;

    public function __construct()
    {
        $this->entitlementService = App::make(EntitlementService::class);
    }

    /**
     * @param string $code 权益码，由 ThinkPHP 路由中间件元组语法注入。
     *                     正确：Route::middleware([['api_entitlement', ['mall']]])
     *                     错误：Route::middleware(['api_entitlement:mall']) — route 级不解析 ":"
     */
    public function handle(Request $request, Closure $next, string $code): Response
    {
        $tenant = TenantContext::current();
        if ($tenant === null || $tenant->isPlatform()) {
            throw new BusinessException(lang('auth.entitlement_unauthorized'), 401);
        }

        if (!$this->entitlementService->has($tenant->id(), $code)) {
            throw new BusinessException(
                lang('auth.entitlement_not_granted', ['code' => $code]),
                403
            );
        }

        return $next($request);
    }
}
