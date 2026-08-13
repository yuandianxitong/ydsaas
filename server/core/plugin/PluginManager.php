<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use app\repository\plugin\PluginRepository;

/**
 * 插件运行时入口。
 *
 * bootAll() 在框架 boot 阶段调用：
 *   1. 扫描 plugins/ 目录，把每个有效 manifest 写入 PluginRegistry（提供运行期只读视图）
 *   2. 对 DB 中 status=ENABLED 的插件，调 PluginLoader 完成 PSR-4 挂载 + 路由 require
 *
 * 设计：plugins/ 目录为空或 plugins 表无 enabled 行时，bootAll() 无副作用。
 * DB 不可用（首次安装前）时不抛错，仅写 registry，不挂 loader。
 */
class PluginManager
{
    /**
     * bootAll() 收集的、按应用分组的待挂载路由条目。
     * 键为 appName（tenantapi/platformapi/api），值为 PluginLoader::load() 返回的 entry 列表。
     *
     * @var array<string, array<int, array{code: string, entitlement: string, routeFile: string}>>
     */
    private array $routesByApp = [];

    /**
     * bootAll() 收集的、按插件 code 分组的 console 命令类声明（仅启用插件）。
     * Task 1（shop P2 后端计划）：manifest 顶层 `console: string[]`。
     *
     * 值的元素类型不保证为 string——validator 为 null（如部分测试直接 new
     * PluginManager 不传 validator）时不会校验 manifest，运行时仍需 is_string 兜底。
     *
     * @var array<string, array<int, mixed>>
     */
    private array $consoleByCode = [];

    public function __construct(
        private readonly string                    $pluginsDir,
        private readonly PluginRegistry            $registry,
        private readonly ?PluginLoader             $loader = null,
        private readonly ?PluginRepository         $pluginRepo = null,
        private readonly ?PluginManifestValidator  $validator = null,
        private readonly ?PluginEventRegistrar     $eventRegistrar = null,
    ) {
    }

    public function bootAll(): void
    {
        if (!is_dir($this->pluginsDir)) {
            return;
        }

        // 1. 扫描 manifest 写 registry（与 DB 无关，install/upgrade 工具链需要）
        $manifestByCode = [];
        foreach ($this->scanPluginDirs() as $code => $pluginDir) {
            $manifestFile = $pluginDir . '/plugin.json';
            if (!is_file($manifestFile)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (!is_array($manifest) || ($manifest['code'] ?? '') !== $code) {
                continue;
            }

            // v2.5.1：boot 阶段先跑 Validator —— 防止已启用但 manifest 被手工改坏
            // 的插件进入运行时（破坏路由 / 命名空间映射）。校验失败仅跳过该插件
            // + 写 error_log，DB status 不动，由运维显式排查。
            if ($this->validator !== null) {
                $errors = $this->validator->validate($manifest);
                if (!empty($errors)) {
                    error_log(sprintf(
                        '[plugin:%s] manifest validation failed during boot, skipping. errors=%s',
                        $code,
                        implode(' | ', $errors),
                    ));
                    continue;
                }
            }

            $this->registry->register($code, [
                'version'  => $manifest['version'] ?? '0.0.0',
                'dir'      => $pluginDir,
                'manifest' => $manifest,
            ]);
            $manifestByCode[$code] = ['manifest' => $manifest, 'dir' => $pluginDir];
        }

        // 2. 仅对 DB 中 status=ENABLED 的插件调 PluginLoader 挂 PSR-4 + 注册路由
        if ($this->loader === null || $this->pluginRepo === null) {
            return;
        }
        try {
            $enabled = $this->pluginRepo->listEnabled();
        } catch (\Throwable) {
            // 首次安装前 plugins 表不存在 — 静默跳过
            return;
        }
        foreach ($enabled as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code === '' || !isset($manifestByCode[$code])) {
                continue;
            }
            $entries = $this->loader->load(
                $manifestByCode[$code]['manifest'],
                $manifestByCode[$code]['dir'],
            );
            foreach ($entries as $appName => $entry) {
                $this->routesByApp[$appName][] = $entry;
            }

            // 2.5. console 命令声明（仅启用插件贡献；坏类留到 consoleCommands() 时过滤）。
            $this->consoleByCode[$code] = (array) ($manifestByCode[$code]['manifest']['console'] ?? []);

            // 3. 事件订阅（manifest.events / manifest.subscriber）。
            // 必须在 loader->load 之后：subscriber/handler 类依赖 PSR-4 挂载。
            // 单个插件订阅失败只 error_log，不拖垮整个 boot。
            if ($this->eventRegistrar !== null) {
                try {
                    $this->eventRegistrar->register($code, $manifestByCode[$code]['manifest']);
                } catch (\Throwable $e) {
                    error_log(sprintf('[plugin:%s] 事件订阅注册失败：%s', $code, $e->getMessage()));
                }
            }
        }
    }

    /**
     * 把 bootAll() 阶段为某个应用收集到的插件路由条目挂载进当前路由表。
     * 由 app/{appName}/route/plugins.php 在框架分发到该应用时调用，天然按
     * 应用作用域生效，修复跨应用同名 pathinfo 冲突（详见 PluginLoader::mountAppRoutes）。
     *
     * PluginManager 是容器单例（AppService::register 里 bind 的闭包首次 make 后被
     * Container 缓存进 $instances，同一请求内 App::getInstance()->make() 复用同一实例），
     * 因此这里读到的 $routesByApp 就是 bootAll() 收集的结果，不会丢失。
     *
     * loader 为 null（如首次安装前 DB 不可用）时静默返回，不抛错。
     *
     * appName === 'api' 时额外挂载 'api_auth' 桶（Task 2，shop P2 后端计划）：
     * C 端认证态路由与匿名路由共享同一个应用分发入口（app/api/route/plugins.php
     * 只调一次 mountRoutesFor('api')），因此由本方法内部同时挂两组，
     * 调用方无需感知 api_auth 这个独立桶的存在。
     */
    public function mountRoutesFor(string $appName): void
    {
        if ($this->loader === null) {
            return;
        }
        $this->loader->mountAppRoutes($appName, $this->routesByApp[$appName] ?? []);
        if ($appName === 'api') {
            $this->loader->mountAppRoutes('api_auth', $this->routesByApp['api_auth'] ?? []);
        }
    }

    /**
     * 扁平化所有启用插件声明的 console 命令类，class_exists 守卫过滤——
     * manifest 里声明了但类不存在（拼写错误 / 忘记提交文件）只 error_log 跳过，
     * 不拖垮 CLI（php think 任何命令都会触发 Console::starting 回调）。
     *
     * @return array<int, class-string>
     */
    public function consoleCommands(): array
    {
        $commands = [];
        foreach ($this->consoleByCode as $code => $classes) {
            foreach ($classes as $cls) {
                if (!is_string($cls) || $cls === '') {
                    continue;
                }
                if (!class_exists($cls)) {
                    error_log(sprintf('[plugin:%s] console 命令类不存在，已跳过：%s', $code, $cls));
                    continue;
                }
                if (!is_subclass_of($cls, \think\console\Command::class)) {
                    error_log(sprintf('[plugin:%s] console 类不是 Command 子类，已跳过：%s', $code, $cls));
                    continue;
                }
                $commands[] = $cls;
            }
        }
        return $commands;
    }

    /**
     * @return iterable<string, string> code => absolute dir path
     */
    private function scanPluginDirs(): iterable
    {
        foreach (scandir($this->pluginsDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $full = $this->pluginsDir . '/' . $entry;
            if (is_dir($full)) {
                yield $entry => $full;
            }
        }
    }
}
