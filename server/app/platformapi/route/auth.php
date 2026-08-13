<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 平台超管认证路由
// 注意：所有路由都要先经过 platform_context（校验请求 host 在白名单）
// login 不需要 platform_auth；info / logout 需要

// 公开端点（无需登录）
Route::group('auth', function () {
    Route::get('captcha', 'v1.AuthController/captcha');
    Route::post('login', 'v1.AuthController/login');
})->middleware(['locale', 'platform_context', 'platform_login_rate_limit']);

// 需要登录的端点
Route::group('auth', function () {
    Route::get('me',       'v1.AuthController/me');
    Route::get('info',     'v1.AuthController/info');
    Route::post('refresh', 'v1.AuthController/refresh');
    Route::post('logout',  'v1.AuthController/logout');
})->middleware(['locale', 'platform_context', 'platform_auth']);
