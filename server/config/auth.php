<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
return [
    // JWT配置 - 双 scope 隔离
    'jwt' => [
        'algorithm'      => 'HS256',
        'expire'         => env('JWT_EXPIRE', 7200),
        'refresh_expire' => env('JWT_REFRESH_EXPIRE', 86400),

        'tenant' => [
            'key'    => env('JWT_TENANT_SECRET', ''),
            'issuer' => env('JWT_TENANT_ISSUER', 'ydsaas-saas-tenant'),
        ],

        'platform' => [
            'key'    => env('JWT_PLATFORM_SECRET', ''),
            'issuer' => env('JWT_PLATFORM_ISSUER', 'ydsaas-saas-platform'),
        ],
    ],

    // 权限配置
    'permission' => [
        'cache_expire'     => 3600, // 权限缓存时间
        'super_admin_role' => 'super_admin', // 超级管理员角色
    ],
];
