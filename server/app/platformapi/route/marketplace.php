<?php

use think\facade\Route;

Route::group('marketplace', function () {
    // 安装意图：点安装 → 连接账号 → 同步权益 → 判定 ready/purchase_required/incompatible
    Route::post('install-intents',         'v1.marketplace.InstallIntentController/create');
    Route::post('connections/exchange',    'v1.marketplace.ConnectionController/exchange');
    Route::get('connections',              'v1.marketplace.ConnectionController/index');
    Route::get('connections/:id',          'v1.marketplace.ConnectionController/show');
    Route::delete('connections/:id',       'v1.marketplace.ConnectionController/revoke');
    Route::post('connections/:id/rotate-token', 'v1.marketplace.ConnectionController/rotateToken');

    Route::get('categories',                    'v1.marketplace.CatalogController/categories');
    Route::get('apps',                          'v1.marketplace.CatalogController/apps');
    Route::post('sync',                         'v1.marketplace.CatalogController/sync');
    Route::get('apps/:entitlementCode/changelog', 'v1.marketplace.ChangelogController/show');

    Route::post('install', 'v1.marketplace.InstallController/install');
    Route::post('upgrade', 'v1.marketplace.InstallController/upgrade');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
