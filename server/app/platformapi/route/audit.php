<?php
use think\facade\Route;

Route::group('audit', function () {
    Route::get('logs', 'v1.AuditController/logs');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
