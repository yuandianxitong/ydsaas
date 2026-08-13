<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app;

use app\repository\plugin\PluginRepository;
use Composer\Autoload\ClassLoader;
use core\plugin\PluginLoader;
use core\plugin\PluginManager;
use core\plugin\PluginRegistry;
use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register(): void
    {
        $this->app->bind(PluginRegistry::class, function () {
            return new PluginRegistry();
        });

        $this->app->bind(PluginLoader::class, function () {
            // 取 Composer 已注册的 ClassLoader（vendor/autoload.php 在框架启动时已 require）
            $loaders = ClassLoader::getRegisteredLoaders();
            $classLoader = $loaders[array_key_first($loaders)] ?? null;
            if (!$classLoader instanceof ClassLoader) {
                throw new \RuntimeException('Composer ClassLoader 未就绪');
            }
            return new PluginLoader($classLoader);
        });

        $this->app->bind(\core\plugin\PluginEventRegistrar::class, fn () => new \core\plugin\PluginEventRegistrar());

        $this->app->bind(PluginManager::class, function () {
            return new PluginManager(
                pluginsDir: $this->app->getRootPath() . 'plugins',
                registry:   $this->app->make(PluginRegistry::class),
                loader:     $this->app->make(PluginLoader::class),
                pluginRepo: $this->app->make(PluginRepository::class),
                validator:  $this->app->make(\core\plugin\PluginManifestValidator::class),
                eventRegistrar: $this->app->make(\core\plugin\PluginEventRegistrar::class),
            );
        });

        // Stage 2: 新增依赖
        $this->app->bind(\core\plugin\PluginPackageInstaller::class, fn () => new \core\plugin\PluginPackageInstaller());
        $this->app->bind(\core\plugin\PluginManifestValidator::class, fn () => new \core\plugin\PluginManifestValidator());
        $this->app->bind(\core\plugin\PluginSqlRunner::class, fn () => new \core\plugin\PluginSqlRunner(
            app()->make(\app\repository\plugin\PluginMigrationLogRepository::class)
        ));

        // Stage 5: shell 执行器 + builder
        $this->app->bind(\core\plugin\contracts\ShellExecutor::class, fn () => new \core\plugin\ProcShellExecutor());
        $this->app->bind(\core\plugin\PluginBuilder::class, function () {
            return new \core\plugin\PluginBuilder(
                $this->app->make(\core\plugin\contracts\ShellExecutor::class)
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(PluginManager::class)->bootAll();

        // 插件 console 命令：仅 CLI 场景注册（HTTP 请求无 Console 实例，starting 回调不触发）。
        // Task 1（shop P2 后端计划）：manifest 顶层 console 数组 → PluginManager::consoleCommands()。
        \think\Console::starting(function (\think\Console $console) {
            $commands = $this->app->make(PluginManager::class)->consoleCommands();
            foreach ($commands as $cls) {
                $console->addCommand($cls);
            }
        });
    }
}
