<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

use think\facade\Route;

// 租户后台：移动端配置（启动首页 / 主题 / Logo / tabBar）
Route::group('mobile', function () {
    Route::get('config',          'v1.mobile.MobileConfigController/get');
    Route::put('config',          'v1.mobile.MobileConfigController/update');
    Route::get('config/eligible', 'v1.mobile.MobileConfigController/eligible');

    // Phase C：构建生命周期
    Route::get('builds',                  'v1.mobile.MobileBuildController/list');
    Route::post('builds',                 'v1.mobile.MobileBuildController/create');
    Route::get('builds/<id>',             'v1.mobile.MobileBuildController/detail');
    Route::post('builds/<id>/requeue',    'v1.mobile.MobileBuildController/requeue');
    Route::post('builds/<id>/release',    'v1.mobile.MobileBuildController/release');
    Route::post('builds/<id>/upload',     'v1.mobile.MobileBuildController/upload');
    Route::post('builds/<id>/cancel',     'v1.mobile.MobileBuildController/cancel');
    // PHP 仅对 POST 填充 $_FILES；小程序 .key 上传必须用 POST（勿改回 PUT）
    Route::post('config/wechat-key',      'v1.mobile.MobileBuildController/saveWechatKey');
    Route::delete('config/wechat-key',    'v1.mobile.MobileBuildController/clearWechatKey');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
