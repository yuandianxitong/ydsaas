<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 文件上传相关路由
// 显式挂 admin_permission：Controller 以 #[PermissionSkip] 声明「已认证即可上传」策略，
// 禁止靠「漏挂中间件」隐式放行；操作日志仍记录以便审计敏感上传。
Route::group('upload', function () {
    Route::post('image', 'v1.upload.UploadController/image');
    Route::post('file', 'v1.upload.UploadController/file');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
