<?php
use think\facade\Route;

// 支付回调（无需登录，公开路由）
Route::group('payment', function () {
    Route::post('notify/alipay', 'v1.payment.PaymentController/alipayNotify');
    Route::post('notify/wechat', 'v1.payment.PaymentController/wechatNotify');
    // dev-only：mock 支付回调触发端点，APP_DEBUG=false 时 404 伪装（不暴露路由存在）
    Route::post('notify/mock', 'v1.payment.PaymentController/mockNotify');
});

// 需要登录的支付接口
Route::group('payment', function () {
    Route::post('create', 'v1.payment.PaymentController/create');
    Route::get('query', 'v1.payment.PaymentController/query');
    Route::post('refund', 'v1.payment.PaymentController/refund');
})->middleware(['tenant_context', 'tenant_status', 'api_auth']);
