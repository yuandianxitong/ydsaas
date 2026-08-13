<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

Route::group('version', function () {
    Route::get('list', 'v1.version.AppVersionController/list');
    Route::get('detail/:id', 'v1.version.AppVersionController/detail');
    Route::post('', 'v1.version.AppVersionController/create');
    Route::put(':id', 'v1.version.AppVersionController/update');
    Route::delete(':id', 'v1.version.AppVersionController/delete');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission']);
