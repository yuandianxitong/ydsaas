<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

use think\facade\Route;

Route::group('plans/:planId', function () {
    Route::get('plugin-grants', 'v1.PluginGrantController/listByPlan');
    Route::put('plugin-grants', 'v1.PluginGrantController/syncByPlan');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
