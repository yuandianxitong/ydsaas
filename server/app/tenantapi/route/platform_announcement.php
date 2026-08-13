<?php
use think\facade\Route;

Route::group('platform-announcement', function () {
    Route::get('', 'v1.PlatformAnnouncementController/index');
    Route::get('unread-count', 'v1.PlatformAnnouncementController/unreadCount');
    Route::get(':id', 'v1.PlatformAnnouncementController/show');
    Route::post(':id/read', 'v1.PlatformAnnouncementController/markAsRead');
})->middleware(['tenant_auth', 'tenant_status']);
