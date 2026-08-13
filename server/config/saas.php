<?php

return [
    // 平台支持的根域名（用于 tenantapi 子域名解析）
    'root_domains' => [
        env('SAAS_ROOT_DOMAIN', 'app.com'),
        // 本地开发可加：'local.app.com'
    ],

    // 本地开发用：当 Host 无法解析子域名时，使用此 tenant_code 作为 fallback
    // 仅在 app_debug=true 时生效，生产环境无影响
    'dev_tenant_code' => env('SAAS_DEV_TENANT_CODE', ''),

    // 平台超管访问的域名白名单
    'platform_domains' => [
        env('SAAS_PLATFORM_DOMAIN', 'admin.app.com'),
        'localhost',   // 本地开发
        '127.0.0.1',   // 本地开发
    ],

    // 宽限期天数（M2 任务实际生效）
    'grace_days' => (int) env('SAAS_GRACE_DAYS', 7),

    // 到期提醒天数（到期前 N 天发送提醒）
    'reminder_days' => [14, 7, 3, 1],

    // 子域名保留词扩展（在 TenantResolver::RESERVED_SUBDOMAINS 之外的额外保留）
    'reserved_subdomains' => [],

    // CORS Origin 白名单
    'cors' => [
        'allowed_origins'   => env('SAAS_CORS_ALLOWED_ORIGINS', ''),
        'allow_credentials' => true,
    ],

    // 租户状态中间件白名单：即使是 frozen 状态，这些路径仍然允许访问
    // 路径是相对于 tenantapi 应用根的 URI（不含 /tenantapi 前缀）
    // 支持尾部 '*' 通配（例如 'subscription/*' 匹配 'subscription/pay'）
    'status_whitelist' => [
        'auth/info',       // 让租户看到自己的 lifecycle 状态
        'auth/logout',     // 允许登出
        'auth/refresh',    // 刷新 token（即使过期也允许，拿到 token 才能续费）
        'subscription/*',  // M3B 追加：下单、付款、查订单
    ],

    // SaaS 级支付配置（平台自己的商户号；不走 system_configs，避免被 tenant scope 污染）
    'payment' => [
        // 支付回调根 URL（外网可达），给 driver 生成 notify_url 用
        // 例：https://admin.app.com
        'notify_base_url' => (string) env('SAAS_PAY_NOTIFY_BASE_URL', ''),

        'wechat' => [
            'enabled'          => (bool) env('SAAS_PAY_WECHAT_ENABLED', false),
            'app_id'           => (string) env('SAAS_PAY_WECHAT_APP_ID', ''),
            'mch_id'           => (string) env('SAAS_PAY_WECHAT_MCH_ID', ''),
            'api_v3_key'       => (string) env('SAAS_PAY_WECHAT_API_V3_KEY', ''),
            'serial_no'        => (string) env('SAAS_PAY_WECHAT_SERIAL_NO', ''),
            'private_key_path' => (string) env('SAAS_PAY_WECHAT_PRIVATE_KEY_PATH', ''),
            'cert_path'        => (string) env('SAAS_PAY_WECHAT_CERT_PATH', ''),
            // 回调路径（相对 notify_base_url），默认就是 M3B 约定的路由
            'notify_path'      => '/api/saas/notify/wechat',
        ],

        'alipay' => [
            'enabled'     => (bool) env('SAAS_PAY_ALIPAY_ENABLED', false),
            'app_id'      => (string) env('SAAS_PAY_ALIPAY_APP_ID', ''),
            'private_key' => (string) env('SAAS_PAY_ALIPAY_PRIVATE_KEY', ''),
            'public_key'  => (string) env('SAAS_PAY_ALIPAY_PUBLIC_KEY', ''),
            'notify_path' => '/api/saas/notify/alipay',
        ],
    ],

    // v2.7.0：插件 migration file_hash 校验严格度
    //   false（默认）→ hash 漂移仅记 error_log
    //   true          → hash 漂移直接 422 拒绝继续 up/upgrade
    // 生产环境推荐 true；多环境部署 / CI 构建 hash 不稳定时保持 false
    'plugin_migration_strict_hash' => (bool) env('SAAS_PLUGIN_MIGRATION_STRICT_HASH', false),

    // v2.8.0：官方市场客户端配置
    // v2.9.0：sync / license / grace / token rotate / audit 调度参数
    'marketplace' => [
        // 开放 API 基址（服务器到服务器：/api/open/instances/*、/api/open/market/*）
        'default_site_base_url' => env('SAAS_MARKETPLACE_DEFAULT_SITE', 'https://www.dev007.cn'),
        // 浏览器面向的前端站点基址（授权页 /connect/saas、购买页 /market/xxx）。
        // 留空 = 与 default_site_base_url 相同（生产环境前端与开放 API 同源同根）。
        // 本地开发若 Site 前端挂在 /pc 而开放 API 在根，则二者需分开配置：
        //   SAAS_MARKETPLACE_DEFAULT_SITE = http://localhost:8005      (开放 API)
        //   SAAS_MARKETPLACE_WEB_BASE     = http://localhost:8005/pc   (前端页面)
        'web_base_url'          => env('SAAS_MARKETPLACE_WEB_BASE', ''),
        'public_key_ttl'        => 7 * 24 * 3600,
        'install_lock_ttl'      => 60,
        'download_timeout'      => 120,
        'http_timeout'          => 15,

        // v2.9.0
        'sync_interval_hours'                  => (int) env('SAAS_MARKETPLACE_SYNC_HOURS', 1),
        'license_evaluate_interval_hours'      => (int) env('SAAS_MARKETPLACE_LICENSE_EVAL_HOURS', 6),
        'grace_period_days'                    => (int) env('SAAS_MARKETPLACE_GRACE_DAYS', 14),
        'site_unreachable_grace_trigger_hours' => 24,
        'token_rotate_threshold_days'          => 90,
        'token_rotate_warn_days'               => 7,
        'audit_report_enabled'                 => (bool) env('SAAS_MARKETPLACE_AUDIT_ENABLED', true),
        'audit_timeout_seconds'                => 3,
    ],
];
