<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

Route::group('plans', function () {
    Route::get('',          'v1.PlanController/index');
    Route::get('options',   'v1.PlanController/options');  // 必须在 :id 之前
    Route::get(':id',       'v1.PlanController/show');
    Route::post('',         'v1.PlanController/store');
    Route::put(':id',       'v1.PlanController/update');
    Route::delete(':id',    'v1.PlanController/destroy');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
