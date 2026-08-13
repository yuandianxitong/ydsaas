<?php

declare(strict_types=1);

namespace app\platformapi\middleware;

use core\http\Middleware;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\model\saas\PlatformAdmin;
use Closure;
use ReflectionMethod;
use think\facade\Log;
use think\Request;
use think\Response;

class PlatformPermissionMiddleware extends Middleware
{
    private const SKIP = '__skip__';

    protected static array $permissionCache = [];

    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->platformAdminId ?? 0;
        if (!$adminId) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        $admin = PlatformAdmin::find($adminId);
        if (!$admin) {
            return $this->errorResponse(lang('auth.please_login'), 401);
        }

        // Super admin skips all permission checks
        if ($admin->is_super) {
            $request->platformAdmin = $admin;
            return $next($request);
        }

        $permissionName = $this->resolvePermission($request);

        if ($permissionName === self::SKIP) {
            // 明确标记了 #[PermissionSkip]
            $request->platformAdmin = $admin;
            return $next($request);
        }

        if (!$permissionName) {
            // 无注解 → 默认拒绝（安全兜底）
            return $this->errorResponse(lang('auth.permission_denied'), 403);
        }

        if (!$admin->hasPermission($permissionName)) {
            return $this->errorResponse(lang('auth.permission_denied'), 403);
        }

        $request->platformAdmin = $admin;
        return $next($request);
    }

    protected function resolvePermission(Request $request): string
    {
        try {
            $rule = $request->rule();
            if (!$rule) {
                return '';
            }

            $dispatch = $rule->getRoute();
            if (!is_string($dispatch)) {
                return '';
            }

            if (str_contains($dispatch, '@')) {
                [$controllerClass, $action] = explode('@', $dispatch, 2);
                $controllerClass = ltrim($controllerClass, '\\');
            } elseif (str_contains($dispatch, '/')) {
                [$controllerPath, $action] = explode('/', $dispatch, 2);
                $controllerClass = 'app\\platformapi\\controller\\' . str_replace('.', '\\', $controllerPath);
            } else {
                return '';
            }

            $cacheKey = $controllerClass . '::' . $action;
            if (array_key_exists($cacheKey, static::$permissionCache)) {
                return static::$permissionCache[$cacheKey];
            }

            if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
                return '';
            }

            $ref = new ReflectionMethod($controllerClass, $action);

            if (!empty($ref->getAttributes(PermissionSkip::class))) {
                return static::$permissionCache[$cacheKey] = self::SKIP;
            }

            $permAttrs = $ref->getAttributes(Permission::class);
            if (!empty($permAttrs)) {
                $perm = $permAttrs[0]->newInstance();
                return static::$permissionCache[$cacheKey] = $perm->value;
            }

            Log::warning("Platform controller method missing permission annotation: {$cacheKey}");
            return static::$permissionCache[$cacheKey] = '';

        } catch (\Throwable) {
            return '';
        }
    }
}
