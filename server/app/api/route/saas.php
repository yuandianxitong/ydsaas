<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
use think\facade\Route;

// SaaS 支付回调（匿名路由，由签名校验身份）
Route::group('saas/notify', function () {
    Route::post('wechat', 'v1.saas.SaasPayNotifyController/wechatNotify');
    Route::post('alipay', 'v1.saas.SaasPayNotifyController/alipayNotify');
});
