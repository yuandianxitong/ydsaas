<?php
use think\facade\Route;

Route::group('refund', function () {
    Route::get('', 'v1.payment.RefundController/index');
    Route::get(':id', 'v1.payment.RefundController/show');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);

Route::post('payment-order/:orderId/refund', 'v1.payment.RefundController/refund')
    ->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
