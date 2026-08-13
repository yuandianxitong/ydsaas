<?php
// +----------------------------------------------------------------------
// | 产品授权（对接官网 Site）
// +----------------------------------------------------------------------

return [
    // 官网授权中心
    'site_base_url' => rtrim((string) env('LICENSE_SITE_BASE_URL', 'https://www.dev007.cn'), '/'),

    // 产品标识，须与 Site products.slug 一致
    'product_slug' => (string) env('LICENSE_PRODUCT_SLUG', 'ydsaas'),

    // 部署域名（空则自动取 HTTP_HOST）
    'domain' => (string) env('LICENSE_DOMAIN', ''),

    // 本地缓存宽限天数（官网不可达时仍视为有效）
    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 14),

    // 心跳间隔（秒），命令 license:heartbeat 使用
    'heartbeat_interval' => (int) env('LICENSE_HEARTBEAT_INTERVAL', 86400),

    // 本地状态文件（相对 runtime 路径）
    'state_file' => 'license/state.json',

    // 是否强制要求平台授权才能连接官方市场
    // false：未激活授权时仍可连接（兼容存量）；true：必须 active/grace 才可连接
    'enforce_marketplace' => (bool) env('LICENSE_ENFORCE_MARKETPLACE', false),
];
