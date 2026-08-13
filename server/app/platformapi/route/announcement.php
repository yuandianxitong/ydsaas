<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

// 平台公告 CRUD（PlatformAnnouncementController 此前缺路由注册，导致 /platformapi/announcement 404）
Route::group('announcement', function () {
    Route::get('',            'v1.PlatformAnnouncementController/index');
    Route::get(':id',         'v1.PlatformAnnouncementController/show');
    Route::post('',           'v1.PlatformAnnouncementController/store');
    Route::put(':id/publish', 'v1.PlatformAnnouncementController/publish'); // 子资源路由必须在 :id 之前
    Route::put(':id',         'v1.PlatformAnnouncementController/update');
    Route::delete(':id',      'v1.PlatformAnnouncementController/delete');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
