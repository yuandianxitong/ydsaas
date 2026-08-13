<?php
use think\facade\Route;

// 显式挂 platform_permission：上传受 platform.file.upload 控制，禁止靠「漏挂中间件」隐式放行
Route::group('upload', function () {
    Route::post('image', 'v1.upload.UploadController/image');
    Route::post('file', 'v1.upload.UploadController/file');
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);
