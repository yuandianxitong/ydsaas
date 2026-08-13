<?php
// +----------------------------------------------------------------------
// | 会话设置
// +----------------------------------------------------------------------

return [
    // session name
    'name'           => 'PHPSESSID',
    // SESSION_ID的提交变量,解决flash上传跨域
    'var_session_id' => '',
    // 驱动方式 支持file cache redis
    'type'           => env('SESSION_TYPE', 'redis'),
    // Redis 连接配置
    'host'           => env('REDIS_HOST', '127.0.0.1'),
    'port'           => (int) env('REDIS_PORT', 6379),
    'password'       => env('REDIS_PASSWORD', ''),
    'select'         => (int) env('REDIS_DB_SESSION', 1),
    // 过期时间（秒）
    'expire'         => (int) env('SESSION_EXPIRE', 1440),
    // 前缀
    'prefix'         => 'sess:',
];
