<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\middleware;

use core\http\Middleware;
use core\auth\TokenManager;
use core\exception\AuthException;
use core\tenant\TenantContext;
use Closure;
use think\Request;
use think\Response;

/**
 * 平台超管 token 校验中间件
 *
 * 与 TenantAuthMiddleware 物理隔离：
 *   - 使用 TokenManager::scope('platform') 独立 secret/issuer
 *   - 要求 PlatformContextMiddleware 已在前面执行（TenantContext is_platform=true）
 *   - 拒绝任何非平台 scope 的 token（scope mismatch / issuer mismatch）
 */
class PlatformAuthMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. 必须先经过 PlatformContextMiddleware 设置 is_platform=true
        $ctx = TenantContext::current();
        if ($ctx === null || !$ctx->isPlatform()) {
            return $this->errorResponse('platform context required', 401);
        }

        // 2. 取出 token 并用 platform scope 校验
        $tokenManager = TokenManager::scope('platform');
        $token = $tokenManager->getTokenFromHeader();

        if (!$token) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        try {
            $payload = $tokenManager->verify($token);

            // 3. 注入请求级用户信息
            $request->platformAdminId = (int) ($payload['platform_admin_id'] ?? 0);
            $request->username        = (string) ($payload['username'] ?? '');
        } catch (AuthException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }

        return $next($request);
    }
}
