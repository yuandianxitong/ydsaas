<?php
declare(strict_types=1);

namespace app\api\middleware;

use core\http\Middleware;
use core\auth\TokenManager;
use core\exception\AuthException;
use core\tenant\TenantContext;
use Closure;
use think\Request;
use think\Response;

class ApiAuthMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenManager = TokenManager::scope('tenant');
        $authorization = (string) $request->header('Authorization', '');
        $token = str_starts_with($authorization, 'Bearer ')
            ? substr($authorization, 7)
            : null;

        if (!$token) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        try {
            $payload = $tokenManager->verify($token);

            // 检查是否为用户端Token
            if (($payload['type'] ?? '') !== 'user') {
                return $this->errorResponse(lang('auth.token_invalid'), 401);
            }

            $tenant = TenantContext::current();
            if ($tenant === null || $tenant->isPlatform()) {
                return $this->errorResponse(lang('auth.token_invalid') . ': tenant context missing', 401);
            }

            $tokenTenantId = (int) ($payload['tenant_id'] ?? 0);
            if ($tokenTenantId <= 0) {
                return $this->errorResponse(lang('auth.token_invalid') . ': tenant missing', 401);
            }
            if ($tokenTenantId !== $tenant->id()) {
                return $this->errorResponse(lang('auth.token_invalid') . ': tenant mismatch', 401);
            }

            $request->userId = (int)($payload['user_id'] ?? 0);
            $request->tenantId = $tenant->id();

        } catch (AuthException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }

        return $next($request);
    }
}
