<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\tenant\middleware;

use Closure;
use core\http\Middleware;
use core\tenant\TenantContext;
use think\Request;
use think\Response;
use think\facade\Config;

/**
 * TenantStatusMiddleware
 *
 * 根据当前 TenantContext 中的 lifecycle_state，拦截或放行请求：
 *   - active / trial：放行
 *   - grace：读操作（GET/HEAD/OPTIONS）放行，写操作（POST/PUT/PATCH/DELETE）返回 402
 *   - frozen / disabled：所有请求返回 402，除非 URI 在 saas.status_whitelist 白名单内
 *
 * 必须在 TenantContextMiddleware 之后、tenant_auth 之后挂载。
 * 如果没有 TenantContext 或处于 platform 上下文（例如 platformapi 场景），直接放行。
 */
class TenantStatusMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $ctx = TenantContext::current();

        // 没有租户上下文（platformapi / CLI / 边缘路径），不拦截
        if ($ctx === null || $ctx->isPlatform()) {
            return $next($request);
        }

        $tenantData = $ctx->tenantData();
        $lifecycleState = (string) ($tenantData['lifecycle_state'] ?? 'active');

        if ($lifecycleState === 'active' || $lifecycleState === 'trial') {
            return $next($request);
        }

        $whitelist = (array) Config::get('saas.status_whitelist', []);
        $pathInfo = ltrim((string) $request->pathinfo(), '/');

        if ($this->pathIsWhitelisted($pathInfo, $whitelist)) {
            return $next($request);
        }

        // grace: 只拦截写操作
        if ($lifecycleState === 'grace') {
            if ($this->isReadMethod($request->method())) {
                return $next($request);
            }
            return $this->blocked(lang('messages.tenant_grace_readonly'));
        }

        // frozen / disabled: 全部拦截
        if ($lifecycleState === 'frozen') {
            return $this->blocked(lang('messages.tenant_frozen'));
        }
        if ($lifecycleState === 'disabled') {
            return $this->blocked(lang('messages.tenant_disabled'));
        }

        // 未知状态：拦截（保守策略）
        return $this->blocked(lang('messages.tenant_status_abnormal') . '：' . $lifecycleState);
    }

    /**
     * path 可以是精确字符串或带 '*' 通配尾（例如 'subscription/*' 匹配 'subscription/pay'）
     */
    private function pathIsWhitelisted(string $path, array $whitelist): bool
    {
        foreach ($whitelist as $pattern) {
            $pattern = (string) $pattern;
            if ($pattern === '') {
                continue;
            }
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2);
                if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                    return true;
                }
                continue;
            }
            if ($path === $pattern) {
                return true;
            }
        }
        return false;
    }

    private function isReadMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function blocked(string $message): Response
    {
        return Response::create([
            'code'    => 402,
            'message' => $message,
            'data'    => null,
        ], 'json', 402);
    }
}
