<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

use think\facade\Route;

Route::group('plugin-builds', function () {
    Route::get('', 'v1.PluginBuildController/index');
    Route::post('rebuild', 'v1.PluginBuildController/rebuild');   // 必须在 :id 之前
    Route::get(':id', 'v1.PluginBuildController/show');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
