<?php

use think\facade\Route;

Route::group('pc', function () {
    Route::get('config', 'v1.pc.PcConfigController/get');
    Route::put('config', 'v1.pc.PcConfigController/update');
    Route::get('config/options', 'v1.pc.PcConfigController/options');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
