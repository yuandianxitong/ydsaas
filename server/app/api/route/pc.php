<?php

use think\facade\Route;

Route::group('pc', function () {
    Route::get('config', 'v1.pc.PcConfigController/get');
    Route::get('diy-page', 'v1.pc.PcConfigController/diyPage');
})->middleware(['tenant_context', 'tenant_status']);
