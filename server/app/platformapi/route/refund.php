<?php
use think\facade\Route;

Route::group('refund', function () {
    Route::get('', 'v1.RefundController/index');
    Route::get(':id', 'v1.RefundController/show');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);

Route::post('orders/:orderId/refund', 'v1.RefundController/refund')
    ->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
