<?php
declare(strict_types=1);

namespace core\tenant\middleware;

use Closure;
use core\exception\BusinessException;
use core\http\Middleware;
use core\tenant\TenantContext;
use think\Request;
use think\Response;
use think\facade\Config;

/**
 * platformapi 应用使用的中间件：
 *   1. 校验 Host 必须在 saas.platform_domains 白名单
 *   2. 强制 TenantContext 为 isPlatform=true / id=0
 */
class PlatformContextMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::reset();

        $platformDomains = (array) Config::get('saas.platform_domains', ['admin.app.com']);
        $host = strtolower(explode(':', $request->host(true))[0]);

        if (!in_array($host, array_map('strtolower', $platformDomains), true)) {
            throw new BusinessException(
                lang('messages.platform_domain_not_allowed', ['host' => $host]),
                404
            );
        }

        TenantContext::set([
            'id'          => 0,
            'code'        => '',
            'is_platform' => true,
            'raw'         => ['domain' => $host],
        ]);

        return $next($request);
    }
}
