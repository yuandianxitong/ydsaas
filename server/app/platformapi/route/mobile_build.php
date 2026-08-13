<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

use think\facade\Route;

// 平台超管：移动端构建全局监控
Route::group('mobile-builds', function () {
    Route::get('',            'v1.MobileBuildController/index');
    Route::post('force-fail', 'v1.MobileBuildController/forceFailStuck');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
