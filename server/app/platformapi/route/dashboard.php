<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

Route::group('dashboard', function () {
    Route::get('stats', 'v1.DashboardController/stats');
    Route::get('extended-stats', 'v1.DashboardController/extendedStats');
    Route::get('revenue-trend', 'v1.DashboardController/revenueTrend');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission']);
