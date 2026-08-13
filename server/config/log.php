<?php

return [
    // 默认通道：通过 LOG_CHANNEL 环境变量切换
    // file = 文件日志（开发环境），stdout = JSON 标准输出（生产/Docker 环境）
    'default'      => env('LOG_CHANNEL', 'file'),
    'level'        => [],
    'type_channel' => [],
    'close'        => false,
    'processor'    => null,

    'channels'     => [
        'file' => [
            'type'           => 'File',
            'path'           => '',
            'single'         => false,
            'apart_level'    => [],
            'max_files'      => 30,
            'json'           => false,
            'processor'      => \core\log\TenantLogProcessor::class,
            'close'          => false,
            'format'         => '[%s][%s] %s',
            'realtime_write' => false,
        ],
        'stdout' => [
            'type'           => 'File',
            'path'           => 'php://stdout',
            'single'         => true,
            'json'           => true,
            'processor'      => \core\log\TenantLogProcessor::class,
            'realtime_write' => true,
        ],
    ],
];
