<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

Route::group('plugins', function () {
    Route::get('', 'v1.PluginController/index');
    Route::get('options', 'v1.PluginController/options');
    Route::get(':id', 'v1.PluginController/show');
    Route::post('upload', 'v1.PluginController/upload');
    Route::post('install-from-disk', 'v1.PluginController/installFromDisk');
    Route::post(':id/install', 'v1.PluginController/install');
    Route::post(':id/uninstall', 'v1.PluginController/uninstall');
    Route::post(':id/disable', 'v1.PluginController/disable');
    Route::post(':id/enable', 'v1.PluginController/enable');
    Route::post(':id/upgrade', 'v1.PluginController/upgrade');
    Route::delete(':id/data', 'v1.PluginController/purgeData');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
