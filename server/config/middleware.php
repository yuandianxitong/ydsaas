<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'tenant_auth' => app\tenantapi\middleware\TenantAuthMiddleware::class,
        'admin_permission' => app\tenantapi\middleware\AdminPermissionMiddleware::class,
        'admin_log' => app\tenantapi\middleware\AdminLogMiddleware::class,
        'entitlement' => app\tenantapi\middleware\EntitlementMiddleware::class,
        'api_auth' => app\api\middleware\ApiAuthMiddleware::class,
        'api_rate_limit' => app\api\middleware\ApiRateLimitMiddleware::class,
        'api_sms_rate_limit' => app\api\middleware\SmsRateLimitMiddleware::class,
        'api_entitlement' => app\api\middleware\ApiEntitlementMiddleware::class,

        // SaaS 平台后台（platformapi）
        'tenant_context'   => core\tenant\middleware\TenantContextMiddleware::class,
        'tenant_status'    => core\tenant\middleware\TenantStatusMiddleware::class,
        'platform_context' => core\tenant\middleware\PlatformContextMiddleware::class,
        'platform_auth'    => app\platformapi\middleware\PlatformAuthMiddleware::class,
        'platform_permission' => app\platformapi\middleware\PlatformPermissionMiddleware::class,
        'platform_log'        => app\platformapi\middleware\PlatformLogMiddleware::class,
        'platform_login_rate_limit' => app\platformapi\middleware\PlatformLoginRateLimitMiddleware::class,
        'locale'              => \core\middleware\LocaleMiddleware::class,
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [],
];
