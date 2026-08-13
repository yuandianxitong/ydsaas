<?php
use app\ExceptionHandle;
use app\Request;

// 容器Provider定义文件
return [
    'think\Request'          => Request::class,
    'think\exception\Handle' => ExceptionHandle::class,

    // 移动端构建器：默认绑定到真实实现
    // 测试通过 reflection 替换 builder 属性来注入 fake，不依赖此绑定
    'core\mobile\MobileBuilder' => \core\mobile\DefaultMobileBuilder::class,

    // remote driver 依赖：容器自动注入 CurlRemoteBuildClient 到 RemoteMobileBuildDriver
    'core\mobile\remote\RemoteBuildClient' => \core\mobile\remote\CurlRemoteBuildClient::class,
];
