<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

use think\facade\Route;

// 租户后台：首页装修（DIY）
Route::group('diy', function () {
    Route::get('home',                          'v1.diy.DiyPageController/getHome');
    Route::put('home',                          'v1.diy.DiyPageController/saveHome');
    Route::post('home/publish',                 'v1.diy.DiyPageController/publishHome');
    Route::get('home/versions',                 'v1.diy.DiyPageController/versions');
    Route::get('home/summary',                  'v1.diy.DiyPageController/homeSummary');
    Route::post('home/versions/:id/restore',    'v1.diy.DiyPageController/restoreVersion');
    // 编辑器组件目录（内置 + 插件 widget 元数据）
    Route::get('widgets',                       'v1.diy.DiyPageController/widgets');
    // 编辑器画布预览注水（单组件跑 hydrator 返回真实数据）
    Route::post('widget-preview',               'v1.diy.DiyPageController/widgetPreview');
    // 装修链接目录（内置 + 自建页 + 授权插件链接 + 链接库）
    Route::get('link-catalog',                  'v1.diy.DiyPageController/linkCatalog');
    // 装修链接库 CRUD
    Route::get('links',                         'v1.diy.DiyLinkController/index');
    Route::post('links',                        'v1.diy.DiyLinkController/save');
    Route::put('links/:id',                     'v1.diy.DiyLinkController/update');
    Route::delete('links/:id',                  'v1.diy.DiyLinkController/delete');
    // 自定义页面管理
    Route::get('pages',                              'v1.diy.DiyPageController/listPages');
    Route::post('pages',                             'v1.diy.DiyPageController/createPage');
    Route::put('pages/:id',                          'v1.diy.DiyPageController/updatePage');
    Route::delete('pages/:id',                       'v1.diy.DiyPageController/deletePage');
    Route::post('pages/:id/copy',                    'v1.diy.DiyPageController/copyPage');
    // 自定义页草稿/发布/版本（:key = slug）
    Route::get('pages/:key/summary',                 'v1.diy.DiyPageController/pageSummary');
    Route::get('pages/:key/draft',                   'v1.diy.DiyPageController/getDraftByKey');
    Route::put('pages/:key/draft',                   'v1.diy.DiyPageController/saveDraftByKey');
    Route::post('pages/:key/publish',                'v1.diy.DiyPageController/publishByKey');
    Route::get('pages/:key/versions',                'v1.diy.DiyPageController/versionsByKey');
    Route::post('pages/:key/versions/:id/restore',   'v1.diy.DiyPageController/restoreVersionByKey');
    // 整套皮肤包（主题色 + TabBar + 启动 + DIY）
    Route::post('skin/export', 'v1.diy.SkinPackController/export');
    Route::post('skin/import', 'v1.diy.SkinPackController/import');
    Route::post('skin/apply',  'v1.diy.SkinPackController/apply');
    Route::get('skin/official',              'v1.diy.SkinPackController/official');
    Route::get('skin/official/:code/download', 'v1.diy.SkinPackController/officialDownload')->pattern(['code' => '[\w\-]+']);
    Route::get('skin/market',                'v1.diy.SkinPackController/marketList');
    Route::post('skin/market/install',       'v1.diy.SkinPackController/marketInstall');
})->middleware(['tenant_auth', 'tenant_status', 'admin_permission', 'admin_log']);
