<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

// 插件路由按应用挂载：只有当前应用的插件路由组会注册，
// 修复跨应用同名路径被先注册组抢走的冲突（如 C 端 /api/shop/product/list
// 被 tenantapi 的 product/list + tenant_auth 拦下 401）。
\think\facade\App::getInstance()
    ->make(\core\plugin\PluginManager::class)
    ->mountRoutesFor('tenantapi');
