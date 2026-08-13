<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

use think\facade\Route;

Route::group('plugin', function () {
    Route::get('', 'v1.plugin.PluginController/index');
    Route::post(':id/enable', 'v1.plugin.PluginController/enable');
    Route::post(':id/disable', 'v1.plugin.PluginController/disable');
    Route::get(':pluginCode/config', 'v1.plugin.PluginController/getConfig');
    Route::put(':pluginCode/config', 'v1.plugin.PluginController/updateConfig');
    Route::post(':id/purchase', 'v1.plugin.PluginController/purchase');
    Route::post(':id/testdata', 'v1.plugin.PluginController/testdata');
    Route::get('orders', 'v1.plugin.PluginController/orders');
    Route::get(':code/config-schema', 'v1.plugin.PluginController/configSchema');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
