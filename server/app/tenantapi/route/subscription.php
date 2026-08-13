<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

// 订阅 + 支付端点
Route::group('subscription', function () {
    Route::get('current', 'v1.subscription.SubscriptionController/current');
    Route::get('plans', 'v1.subscription.SubscriptionController/plans');
    Route::get('pending-renewal', 'v1.subscription.SubscriptionController/pendingRenewal');
    Route::post('create-order', 'v1.subscription.SubscriptionController/createOrder');
    Route::post('pay', 'v1.subscription.SubscriptionController/pay');
    Route::get('query-order', 'v1.subscription.SubscriptionController/queryOrder');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
