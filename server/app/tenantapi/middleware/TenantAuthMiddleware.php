<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\middleware;

use core\http\Middleware;
use core\auth\TokenManager;
use core\exception\AuthException;
use core\tenant\TenantContext;
use Closure;
use think\Request;
use think\Response;

class TenantAuthMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenManager = TokenManager::scope('tenant');
        $token = $tokenManager->getTokenFromHeader();

        if (!$token) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        try {
            $payload = $tokenManager->verify($token);

            // 检查是否为管理员Token
            if (($payload['type'] ?? '') !== 'admin') {
                return $this->errorResponse(lang('auth.token_invalid'), 401);
            }

            // 校验 token 中的 tenant_id 与当前 TenantContext 一致
            // 注意：TenantContext 由 TenantContextMiddleware 在本中间件之前设置好
            $payloadTenantId = (int) ($payload['tenant_id'] ?? 0);
            $ctx = TenantContext::current();
            if ($ctx !== null) {
                $ctxTenantId = $ctx->id();
                if ($payloadTenantId !== 0 && $ctxTenantId !== 0 && $payloadTenantId !== $ctxTenantId) {
                    return $this->errorResponse('租户上下文与令牌不匹配', 401);
                }
            }

            // 将用户信息注入请求（强类型以适配 strict_types=1）
            $request->userId = (int) ($payload['admin_id'] ?? 0);
            $request->username = (string) ($payload['username'] ?? '');

        } catch (AuthException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }

        return $next($request);
    }
}
