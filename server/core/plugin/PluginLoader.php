<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use Composer\Autoload\ClassLoader;
use core\exception\BusinessException;

/**
 * 把单个插件注入到运行时：
 *   1. PSR-4 命名空间挂载到 composer ClassLoader
 *   2. require 路由文件（如有）
 *
 * 注意：本类只做"挂载"动作，不读 DB；DB 校验与事件订阅注册
 * （PluginEventRegistrar）由 PluginManager 完成。
 */
class PluginLoader
{
    public function __construct(
        private readonly ClassLoader $classLoader,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest plugin.json 解析后内容
     * @param string $pluginDir 插件根目录绝对路径
     * @return array<string, array{code: string, entitlement: string, routeFile: string}>
     *         键为 appName（tenantapi/platformapi/api/api_auth），仅含 manifest 声明了对应路由文件的应用。
     *         api_auth 是独立于 api 的桶（C 端认证态路由，Task 2/shop P2 后端计划），
     *         二者互不覆盖：同一插件可同时声明 routes.api（匿名）与 routes.api_auth（登录态）。
     *         路由不在此处直接注册——由调用方按当前请求所属应用挑对应键调 mountAppRoutes()，
     *         避免三个应用的路由组无差别混进同一张全局路由表（跨应用同名路径互相抢注）。
     */
    public function load(array $manifest, string $pluginDir): array
    {
        $code = $manifest['code'] ?? '';
        if ($code === '') {
            throw BusinessException::pluginRuntimeError('?', 'load: empty code in manifest');
        }
        $pluginDir = rtrim($pluginDir, '/') . '/';

        // 1. PSR-4 挂载（全局，boot 阶段一次性完成，与请求所属应用无关）
        $psr4 = $manifest['psr4'] ?? [];
        foreach ($psr4 as $namespace => $relPath) {
            if (!str_starts_with($namespace, 'Plugin\\')) {
                throw BusinessException::pluginRuntimeError($code, 'load: namespace must start with Plugin\\');
            }
            $absPath = $pluginDir . ltrim($relPath, '/');
            $this->classLoader->addPsr4($namespace, $absPath);
        }

        // 2. 路由文件只做登记，不在这里 Route::group()。
        //    真正的注册延后到 mountAppRoutes()，由各应用自己的
        //    app/{app}/route/plugins.php 在分发到该应用时调用，
        //    这样一个插件的路由组只会出现在它对应应用的路由表里。
        $routes = $manifest['routes'] ?? [];
        $entitlement = (string) ($manifest['entitlement'] ?? $code);
        $entries = [];
        foreach (['tenantapi', 'platformapi', 'api', 'api_auth'] as $appName) {
            $routeRel = $routes[$appName] ?? null;
            if (!$routeRel) {
                continue;
            }
            $routeFile = $pluginDir . ltrim($routeRel, '/');
            if (!is_file($routeFile)) {
                throw BusinessException::pluginRuntimeError($code, "load: route file not found: {$routeRel}");
            }

            $entries[$appName] = [
                'code' => $code,
                'entitlement' => $entitlement,
                'routeFile' => $routeFile,
            ];
        }

        // 事件订阅由 PluginManager 调 PluginEventRegistrar 注册（本类不负责）
        return $entries;
    }

    /**
     * 把某个应用待挂载的插件路由组逐个注册进当前（该应用的）路由表。
     * 由 app/{appName}/route/plugins.php 在框架分发到对应应用时调用，
     * 天然只在该应用的请求里生效——修复跨应用同名 pathinfo 冲突。
     *
     * 中间件包装逻辑与旧版 load() 内联时完全一致，三个分支的中间件数组不变。
     *
     * @param array<int, array{code: string, entitlement: string, routeFile: string}> $entries
     */
    public function mountAppRoutes(string $appName, array $entries): void
    {
        foreach ($entries as $entry) {
            $code = $entry['code'];
            $entitlement = $entry['entitlement'];
            $routeFile = $entry['routeFile'];

            if ($appName === 'tenantapi') {
                // v2.7.3：route 级中间件传参必须用元组 [alias, [param]]，
                // 字符串 "alias:param" 仅适用于 Controller protected $middleware（被 Dispatch 的 explode 处理）；
                // route 中间件 buildMiddleware 不解析 ":"，原字符串会被当类名 make → ClassNotFoundException。
                \think\facade\Route::group($code, function () use ($routeFile) {
                    require $routeFile;
                })->middleware([
                    'locale', 'tenant_context', 'tenant_auth', 'tenant_status',
                    ['entitlement', [$entitlement]],
                    'admin_permission', 'admin_log',
                ]);
            } elseif ($appName === 'api') {
                // C 端匿名组：访客可访问；不挂 tenant_auth / admin_permission / admin_log。
                // 保留 locale + tenant_context（subdomain 解析）+ tenant_status + api_entitlement。
                // api_entitlement 与 B 端 entitlement 语义不同：C 端没有超管 bypass。
                // C 端登录态路由见下方 'api_auth' 分支（独立桶，与本组互不覆盖）。
                \think\facade\Route::group($code, function () use ($routeFile) {
                    require $routeFile;
                })->middleware([
                    'locale', 'tenant_context', 'tenant_status',
                    ['api_entitlement', [$entitlement]],
                ]);
            } elseif ($appName === 'api_auth') {
                // C 端认证组（Task 2，shop P2 后端计划）：与匿名 api 组共享公共前缀
                // （locale/tenant_context/tenant_status/api_entitlement），在链尾追加
                // 'api_auth'（消费者 JWT 校验，见 config/middleware.php 别名 → ApiAuthMiddleware）。
                // 顺序取 entitlement 在前、api_auth 在后：未开通权益统一提示购买，
                // 而不是因未登录返回不同语义的错误。
                \think\facade\Route::group($code, function () use ($routeFile) {
                    require $routeFile;
                })->middleware([
                    'locale', 'tenant_context', 'tenant_status',
                    ['api_entitlement', [$entitlement]],
                    'api_auth',
                ]);
            } else {
                // platformapi：与官方路由文件（app/platformapi/route/*.php）同链路包裹，
                // 插件 controller 同样受"无 #[Permission]/#[PermissionSkip] 注解 = 403"策略约束。
                \think\facade\Route::group($code, function () use ($routeFile) {
                    require $routeFile;
                })->middleware([
                    'locale', 'platform_context', 'platform_auth', 'platform_permission', 'platform_log',
                ]);
            }
        }
    }
}
