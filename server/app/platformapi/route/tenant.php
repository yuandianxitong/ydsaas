<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 平台超管 → 租户 CRUD
Route::group('tenants', function () {
    Route::get('', 'v1.TenantController/index');
    Route::post('', 'v1.TenantController/store');
    // 子资源路由必须在 :id 之前，否则 /1/admins 会匹配到 show(id="1/admins")
    Route::get(':tenant_id/admins', 'v1.TenantController/admins');
    Route::put(':tenant_id/reset-password', 'v1.TenantController/resetPassword');
    Route::post(':id/offline-renew', 'v1.TenantController/offlineRenew');
    Route::get(':id', 'v1.TenantController/show');
    Route::put(':id', 'v1.TenantController/update');
    Route::delete(':id', 'v1.TenantController/destroy');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission']);
