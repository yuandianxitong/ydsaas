<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('CACHE_DRIVER', 'tenant_redis'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',
            'prefix'     => '',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
        // 租户感知 Redis 缓存（默认驱动）
        'tenant_redis' => [
            'type'       => \core\cache\TenantRedisDriver::class,
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_DB_CACHE', 0),
            'timeout'    => (int) env('REDIS_TIMEOUT', 0),
            'expire'     => (int) env('REDIS_EXPIRE', 3600),
            'persistent' => false,
            'prefix'     => env('REDIS_PREFIX', 'saas') . ':',
            'tag_prefix' => 'tag:',
        ],
        // 原始 Redis（供 RedisCache 等直连场景使用）
        'redis' => [
            'type'       => 'Redis',
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_DB_CACHE', 0),
            'timeout'    => (int) env('REDIS_TIMEOUT', 0),
            'expire'     => (int) env('REDIS_EXPIRE', 3600),
            'persistent' => false,
            'prefix'     => env('REDIS_PREFIX', 'saas') . ':',
            'tag_prefix' => 'tag:',
        ],
    ],
];
