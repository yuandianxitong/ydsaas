<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

use think\facade\Route;

// C 端 UniApp：移动端配置（启动时拉取一次）
Route::group('mobile', function () {
    Route::get('config', 'v1.mobile.MobileConfigController/get');
    Route::get('diy-page', 'v1.mobile.DiyPageController/get');
})->middleware(['tenant_context', 'tenant_status']);
