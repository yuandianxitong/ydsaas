<?php
use think\facade\Route;

// Public system routes (no auth required)
Route::group('system', function () {
    Route::get('config/global', 'v1.system.SystemConfigController/global');
})->middleware(['locale', 'platform_context']);

// Authenticated system routes
Route::group('system', function () {
    // Config management
    Route::group('config', function () {
        Route::get('groups', 'v1.system.SystemConfigController/groups');
        Route::get('', 'v1.system.SystemConfigController/index');
        Route::put(':id', 'v1.system.SystemConfigController/update');
        Route::post('batch-update', 'v1.system.SystemConfigController/batchUpdate');
        Route::post('clear-cache', 'v1.system.SystemConfigController/clearCache');
    });

    // 平台产品授权（对接官网 Site，product_slug=ydsaas）
    Route::group('license', function () {
        Route::get('status', 'v1.system.LicenseController/status');
        Route::post('activate', 'v1.system.LicenseController/activate');
        Route::post('heartbeat', 'v1.system.LicenseController/heartbeat');
        Route::post('clear', 'v1.system.LicenseController/clear');
    });

    // Admin management
    Route::group('admin', function () {
        Route::get('role/options', 'v1.system.PlatformAdminController/roleOptions');
        Route::put('change-password', 'v1.system.PlatformAdminController/changePassword');
        Route::get('', 'v1.system.PlatformAdminController/index');
        Route::get(':id', 'v1.system.PlatformAdminController/show');
        Route::post('', 'v1.system.PlatformAdminController/store');
        Route::put(':id', 'v1.system.PlatformAdminController/update');
        Route::delete(':id', 'v1.system.PlatformAdminController/delete');
        Route::put(':id/status', 'v1.system.PlatformAdminController/updateStatus');
        Route::put(':id/reset-password', 'v1.system.PlatformAdminController/resetPassword');
    });

    // Role management
    Route::group('role', function () {
        Route::get('options', 'v1.system.PlatformRoleController/options');
        Route::get('menu/tree', 'v1.system.PlatformRoleController/menuTree');
        Route::get('', 'v1.system.PlatformRoleController/index');
        Route::get(':id', 'v1.system.PlatformRoleController/show');
        Route::post('', 'v1.system.PlatformRoleController/store');
        Route::put(':id', 'v1.system.PlatformRoleController/update');
        Route::delete(':id', 'v1.system.PlatformRoleController/delete');
        Route::put(':id/assign-permissions', 'v1.system.PlatformRoleController/assignPermissions');
        Route::put(':id/status', 'v1.system.PlatformRoleController/updateStatus');
    });

    // Menu management
    Route::group('menu', function () {
        Route::get('options', 'v1.system.PlatformMenuController/options');
        Route::get('', 'v1.system.PlatformMenuController/index');
        Route::post('', 'v1.system.PlatformMenuController/store');
        Route::put(':id', 'v1.system.PlatformMenuController/update');
        Route::delete(':id', 'v1.system.PlatformMenuController/delete');
        Route::post('batch-sort', 'v1.system.PlatformMenuController/batchSort');
        Route::put(':id/status', 'v1.system.PlatformMenuController/updateStatus');
    });

    // Logs
    Route::group('log', function () {
        Route::get('login', 'v1.system.PlatformLogController/loginLogs');
        Route::get('operation', 'v1.system.PlatformLogController/operationLogs');
    });

    // 定时任务
    Route::group('cron-job', function () {
        Route::get('', 'v1.system.CronJobController/index');
        Route::get(':id/logs', 'v1.system.CronJobController/logs');
        Route::post(':id/clear-logs', 'v1.system.CronJobController/clearLogs');
        Route::post(':id/run', 'v1.system.CronJobController/run');
        Route::put(':id/status', 'v1.system.CronJobController/status');
        Route::get(':id', 'v1.system.CronJobController/show');
        Route::post('', 'v1.system.CronJobController/store');
        Route::put(':id', 'v1.system.CronJobController/update');
        Route::delete(':id', 'v1.system.CronJobController/delete');
    });

    // 代码生成器
    Route::group('generator', function () {
        Route::get('tables', 'v1.system.CodeGeneratorController/tables');
        Route::get('columns', 'v1.system.CodeGeneratorController/columns');
        Route::post('preview', 'v1.system.CodeGeneratorController/preview');
        Route::post('generate', 'v1.system.CodeGeneratorController/generate');
    });

    // 文件管理
    Route::group('file', function () {
        Route::get('groups', 'v1.system.FileController/groups');
        Route::post('move-group', 'v1.system.FileController/moveGroup');
        Route::post('batch-delete', 'v1.system.FileController/batchDelete');
        Route::put(':id/rename', 'v1.system.FileController/rename');
        Route::delete(':id', 'v1.system.FileController/delete');
        Route::get('', 'v1.system.FileController/index');
    });

    // 数据字典
    Route::group('dictionary', function () {
        Route::get('options', 'v1.system.DictionaryController/options');
        Route::get('batch-options', 'v1.system.DictionaryController/batchOptions');
        Route::post('batch-delete', 'v1.system.DictionaryController/batchDelete');
        Route::get(':id/items', 'v1.system.DictionaryController/items');
        Route::post('item', 'v1.system.DictionaryController/storeItem');
        Route::put('item/:id', 'v1.system.DictionaryController/updateItem');
        Route::delete('item/:id', 'v1.system.DictionaryController/deleteItem');
        Route::get(':id', 'v1.system.DictionaryController/show');
        Route::post('', 'v1.system.DictionaryController/store');
        Route::put(':id', 'v1.system.DictionaryController/update');
        Route::delete(':id', 'v1.system.DictionaryController/delete');
        Route::get('', 'v1.system.DictionaryController/index');
    });

    // 权限管理
    Route::group('permission', function () {
        Route::get('tree', 'v1.system.PermissionController/tree');
        Route::post('batch-create', 'v1.system.PermissionController/batchCreate');
        Route::get('', 'v1.system.PermissionController/index');
        Route::post('', 'v1.system.PermissionController/store');
        Route::put(':id', 'v1.system.PermissionController/update');
        Route::delete(':id', 'v1.system.PermissionController/delete');
    });
})->middleware(['locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log']);

// API文档（无需认证，平台端专属）
Route::group('system', function () {
    Route::get('api-doc/openapi.json', 'v1.system.ApiDocController/openapi');
    Route::get('api-doc', 'v1.system.ApiDocController/index');
})->middleware(['locale', 'platform_context']);
