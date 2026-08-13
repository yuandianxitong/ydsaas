<?php
use think\facade\Route;

// 健康检查端点（无需认证）
Route::get('health', 'v1.health.HealthController/check');
