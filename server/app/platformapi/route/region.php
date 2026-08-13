<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

Route::group('region', function () {
    Route::get('list', 'v1.region.RegionController/list');
    Route::get('tree', 'v1.region.RegionController/tree');
    Route::get('detail/:id', 'v1.region.RegionController/detail');
    Route::post('', 'v1.region.RegionController/create');
    Route::put(':id', 'v1.region.RegionController/update');
    Route::delete(':id', 'v1.region.RegionController/delete');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission']);
