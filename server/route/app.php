<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

// v2.7.4：插件图标按需服务 —— 任何子域名都可通过 /plugin-icon/<code>/<file> 拉到。
// 解析逻辑（path traversal 防护 / status=ENABLED 校验 / manifest.icon 申明校验 /
// 单文件大小上限 / Cache-Control）封在 PluginIconResolver。
// DIY widget 图标（渲染器协议扩展）：manifest diy_widgets[].icon 声明的文件，
// 约定放插件 assets/diy/ 下。更具体的路由须注册在通用 /plugin-icon/<code>/<file> 之前。
Route::get('plugin-icon/<code>/assets/diy/<file>', function (string $code, string $file) {
    return (new \core\plugin\PluginIconResolver())->resolveDiyWidgetIcon($code, $file);
})->pattern([
    'code' => '[a-z0-9-]+',
    'file' => '[A-Za-z0-9._-]+',
]);

Route::get('plugin-icon/<code>/<file>', function (string $code, string $file) {
    return (new \core\plugin\PluginIconResolver())->resolve($code, $file);
})->pattern([
    // 路由参数 pattern 提供第一道防线（控制器内还会再校验）
    'code' => '[a-z0-9-]+',
    'file' => '[A-Za-z0-9._-]+',
]);

// 前端 SPA 回退：/platform 下非静态资源的请求统一返回 index.html
// v2.7.7：旧 admin/ 路由对应的 server/public/admin/ 是 upstream ydadmin 残留，已移除；
// 平台前端 vite 构建产物现在落在 server/public/platform/（base 也是 /platform/）。
// 生产环境的 nginx admin.SAAS_ROOT vhost 直接 root 指向 platform dist，不会进 PHP；
// 这条路由仅 dev 直 hit PHP 时兜底。
Route::get('platform/<any?>', function () {
    return response(file_get_contents(public_path() . 'platform/index.html'), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!css/|js/|fonts/|favicon\.ico|assets/).*']);

// PC SPA 回退：/pc 下非静态资源的请求统一返回 index.html
Route::get('pc/<any?>', function () {
    $file = public_path() . 'pc/index.html';
    if (!file_exists($file)) {
        return response('PC site not deployed', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!css/|js/|fonts/|favicon\.ico|_nuxt/).*']);

// 租户后台 SPA 回退：新部署模式下租户后台挂在 /tenant/。
Route::get('tenant/<any?>', function () {
    $file = public_path() . 'tenant/index.html';
    if (!file_exists($file)) {
        return response('Tenant admin not deployed', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!assets/|favicon\.ico).*']);

// Mobile SPA 回退：/mobile 下非静态资源的请求统一返回 index.html
Route::get('mobile/<any?>', function () {
    $file = public_path() . 'mobile/index.html';
    if (!file_exists($file)) {
        return response('Mobile site not deployed', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!css/|js/|fonts/|favicon\.ico|static/).*']);
