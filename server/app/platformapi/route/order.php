<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

// 平台超管 → SaaS 订单管理
Route::group('orders', function () {
    Route::get('', 'v1.OrderController/index');
    Route::get(':id', 'v1.OrderController/show');
    Route::post(':id/mark-paid', 'v1.OrderController/markPaid');
    Route::post(':id/cancel', 'v1.OrderController/cancel');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission']);
