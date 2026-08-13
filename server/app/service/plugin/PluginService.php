<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\model\plugin\Plugin;
use app\model\plugin\PluginBuild;
use app\repository\plugin\PluginRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\AppMenuInstaller;
use core\plugin\contracts\LifecycleHook;
use core\plugin\PluginPackageInstaller;
use core\plugin\PluginSqlRunner;
use core\plugin\PluginRegistry;
use core\runtime\RuntimePaths;
use think\facade\App;
use think\facade\Event;

/**
 * 编排插件安装/卸载，处理事务、回滚、Registry 失效。
 */
class PluginService extends Service
{
    protected PluginRepository $pluginRepo;
    protected \app\repository\plugin\PluginGrantRepository $grantRepo;
    protected PluginPackageInstaller $installer;
    protected PluginSqlRunner $sqlRunner;
    protected PluginRegistry $registry;
    protected PluginBuildService $buildService;
    protected AppMenuInstaller $appMenuInstaller;
    protected \app\service\saas\EntitlementService $entitlementService;
    protected \core\saga\SagaRunner $saga;

    /**
     * 安装插件：解压 zip → 跑 migration up → 调 LifecycleHook.install。
     *
     * @return array<string, mixed>
     */
    public function install(int $pluginId): array
    {
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException('插件不存在', 404);
        }
        // 允许从 INSTALLING / FAILED / UNINSTALLED（软卸载后再装）发起安装
        $status = (int) $plugin['status'];
        if (
            $status !== Plugin::STATUS_INSTALLING
            && $status !== Plugin::STATUS_FAILED
            && $status !== Plugin::STATUS_UNINSTALLED
        ) {
            throw new BusinessException("插件当前状态不可安装（status={$plugin['status']}）", 409);
        }

        $code       = (string) $plugin['code'];
        $manifest   = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);

        // 依赖前置检查：depends 中的每个插件必须已经处于 STATUS_ENABLED。
        $depends = array_filter(array_map('strval', (array) ($manifest['depends'] ?? [])));
        if (!empty($depends)) {
            $installed = $this->pluginRepo->codesInState(array_values($depends), Plugin::STATUS_ENABLED);
            $missing   = array_values(array_diff($depends, $installed));
            if (!empty($missing)) {
                throw new BusinessException(
                    lang('plugin.depends_missing', ['codes' => implode(', ', $missing)]),
                    400
                );
            }
        }

        $pluginsDir = App::getRootPath() . 'plugins';
        $pluginDir  = $pluginsDir . '/' . $code;
        $isBuiltin  = (int) ($plugin['source'] ?? Plugin::SOURCE_ZIP) === Plugin::SOURCE_BUILTIN;
        // 软卸载后再装：代码目录仍在，跳过解压（等同 BUILTIN 语义）
        $skipExtract = $isBuiltin || ($status === Plugin::STATUS_UNINSTALLED && is_dir($pluginDir));

        // 预检：
        //   - BUILTIN / 再装且目录已在：必须有源码目录
        //   - ZIP 首次安装：目录已存在则拒绝，避免覆盖
        if ($skipExtract) {
            if (!is_dir($pluginDir)) {
                throw new BusinessException("插件目录不存在：{$pluginDir}", 409);
            }
        } elseif (is_dir($pluginDir)) {
            throw new BusinessException(
                "目标目录已存在：{$pluginDir}，请手动清理或重新上传 zip 后再试",
                409
            );
        }

        // v2.7.0：install 流程编排为 saga，每个 step 失败时按倒序触发已完成 step 的 compensate
        $version    = (string) ($plugin['version'] ?? '');
        $kind       = (string) ($plugin['kind'] ?? Plugin::KIND_PLUGIN);
        $updateData = [
            'status'       => Plugin::STATUS_ENABLED,
            'installed_at' => date('Y-m-d H:i:s'),
            'last_error'   => '',
            'depends'      => !empty($depends) ? json_encode(array_values($depends), JSON_UNESCAPED_UNICODE) : null,
            'entitlement'  => (string) ($manifest['entitlement'] ?? $manifest['code']),
        ];

        $steps = [
            new \app\service\plugin\steps\ExtractZipStep($pluginDir, $skipExtract, RuntimePaths::resolvePluginPackagePath((string) $plugin['package_path']), $this->installer),
            new \app\service\plugin\steps\SqlInstallStep($pluginDir, $pluginId, $code, $version, $this->sqlRunner),
            new \app\service\plugin\steps\LifecycleInstallStep($manifest),
            new \app\service\plugin\steps\MarkEnabledStep($pluginId, $updateData, $this->pluginRepo),
            new \app\service\plugin\steps\InstallMenusStep($pluginId, $kind, $manifest, $this->appMenuInstaller, $this->grantRepo),
            new \app\service\plugin\steps\EnqueueBuildStep($pluginId, $this->buildService),
        ];

        try {
            $this->saga->run($steps);
            $this->registry->clear();

            return [
                'id'      => $pluginId,
                'code'    => $code,
                'version' => $version,
                'status'  => Plugin::STATUS_ENABLED,
            ];
        } catch (\Throwable $e) {
            // saga 已倒序触发了所有已完成 step 的 compensate；
            // 这里仅记录 last_error（status 已由 MarkEnabledStep 的补偿改回 FAILED）。
            $compErrors = $this->saga->compensationErrors();
            $errMsg = $e->getMessage();
            if (!empty($compErrors)) {
                $errMsg .= ' | 补偿失败：' . json_encode($compErrors, JSON_UNESCAPED_UNICODE);
            }
            $this->pluginRepo->update($pluginId, [
                'status'     => Plugin::STATUS_FAILED,
                'last_error' => substr($errMsg, 0, 1000),
            ]);
            throw new BusinessException('插件安装失败：' . $e->getMessage(), 500);
        }
    }

    /**
     * 软卸载：保留代码目录与业务表，仅改状态为 UNINSTALLED，可二次安装。
     * 清理业务数据请走 purge（会删代码目录 + 跑 uninstall.sql）。
     *
     * @return array<string, mixed>
     */
    public function uninstall(int $pluginId, bool $force = false): array
    {
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException(lang('plugin.not_found'), 404);
        }

        $status = (int) ($plugin['status'] ?? 0);
        if (!in_array($status, [Plugin::STATUS_ENABLED, Plugin::STATUS_DISABLED, Plugin::STATUS_FAILED], true)) {
            throw new BusinessException("插件当前状态不可卸载（status={$status}）", 409);
        }

        if (!$force) {
            $dependents = $this->pluginRepo->findReverseDependents((string) $plugin['code']);
            if (!empty($dependents)) {
                throw new BusinessException(
                    lang('plugin.uninstall_blocked_by_dependents', [
                        'code'       => (string) $plugin['code'],
                        'dependents' => implode(',', $dependents),
                    ]),
                    400
                );
            }
        }

        $code     = (string) $plugin['code'];
        $manifest = is_array($plugin['manifest'] ?? null)
            ? $plugin['manifest']
            : (json_decode((string) ($plugin['manifest'] ?? ''), true) ?: []);

        // 调 Lifecycle.uninstall（仅做注册信息清理，数据与代码保留）
        try {
            $this->invokeLifecycle($manifest, 'uninstall');
        } catch (\Throwable $e) {
            // 卸载阶段 hook 抛错不阻断软卸载流程，仅记日志
            error_log("[plugin:{$code}] uninstall hook failed: " . $e->getMessage());
        }

        $this->pluginRepo->update($pluginId, [
            'status'     => Plugin::STATUS_UNINSTALLED,
            'last_error' => '',
        ]);
        $this->registry->clear();

        $this->buildService->enqueue(PluginBuild::TARGET_PLATFORM, 'uninstall', $pluginId);
        $this->buildService->enqueue(PluginBuild::TARGET_TENANT, 'uninstall', $pluginId);

        // 清空全局权限缓存：插件卸载影响所有租户的权限
        $this->entitlementService->flushAll();

        Event::trigger('plugin.uninstalled', [
            'plugin_id'           => $pluginId,
            'code'                => $code,
            'version'             => (string) ($plugin['version'] ?? ''),
            'distribution_source' => (string) ($plugin['distribution_source'] ?? ''),
        ]);

        return [
            'id'          => $pluginId,
            'code'        => $code,
            'status'      => Plugin::STATUS_UNINSTALLED,
            'uninstalled' => true,
        ];
    }

    /**
     * 平台级临时禁用（v2.27.1，补 STATUS_DISABLED 状态流转）：
     *   - 代码目录与业务数据全部保留，随时 enable() 恢复；
     *   - 下个请求起 PluginManager 不再挂载其 PSR-4/路由；entitlement 缓存清空后，
     *     套餐授权与单独购买权益即刻失效（listByPlan 等查询均按 status=ENABLED 过滤）；
     *   - 不拆租户菜单（保留角色勾选关系，重启用零成本恢复），租户点击会收到权益拦截提示；
     *   - 不触发前端重建（资产保留，恢复更快）；
     *   - 不调 Lifecycle hook（enable/disable hook 是租户级业务语义，平台级开关属基础设施动作）。
     *
     * @return array<string, mixed>
     */
    public function disable(int $pluginId): array
    {
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException(lang('plugin.not_found'), 404);
        }
        if ((int) $plugin['status'] !== Plugin::STATUS_ENABLED) {
            throw new BusinessException("插件当前状态不可禁用（status={$plugin['status']}）", 409);
        }

        $code = (string) $plugin['code'];

        // 与 uninstall 同款依赖保护：被其它启用插件依赖时不允许禁用
        $dependents = $this->pluginRepo->findReverseDependents($code);
        if (!empty($dependents)) {
            throw new BusinessException(
                "插件被依赖，无法禁用：{$code} ← " . implode(',', $dependents),
                400
            );
        }

        $this->pluginRepo->update($pluginId, ['status' => Plugin::STATUS_DISABLED]);
        $this->registry->clear();
        $this->entitlementService->flushAll();

        Event::trigger('plugin.disabled', ['plugin_id' => $pluginId, 'code' => $code]);

        return ['id' => $pluginId, 'code' => $code, 'status' => Plugin::STATUS_DISABLED];
    }

    /**
     * 恢复被平台级禁用的插件（disable() 的逆操作）。
     *
     * @return array<string, mixed>
     */
    public function enable(int $pluginId): array
    {
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException(lang('plugin.not_found'), 404);
        }
        if ((int) $plugin['status'] !== Plugin::STATUS_DISABLED) {
            throw new BusinessException("插件当前状态不可启用（status={$plugin['status']}）", 409);
        }

        $code = (string) $plugin['code'];

        // disable 不删目录；目录缺失说明被人工动过，引导走重新上传安装链路
        $pluginDir = App::getRootPath() . 'plugins/' . $code;
        if (!is_dir($pluginDir) || !is_file($pluginDir . '/plugin.json')) {
            throw new BusinessException("插件代码目录缺失（plugins/{$code}），请重新上传安装", 409);
        }

        $this->pluginRepo->update($pluginId, ['status' => Plugin::STATUS_ENABLED]);
        $this->registry->clear();
        $this->entitlementService->flushAll();

        Event::trigger('plugin.enabled', ['plugin_id' => $pluginId, 'code' => $code]);

        return ['id' => $pluginId, 'code' => $code, 'status' => Plugin::STATUS_ENABLED];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function invokeLifecycle(array $manifest, string $hook): void
    {
        $lifecycleClass = (string) ($manifest['lifecycle'] ?? '');
        if ($lifecycleClass === '' || !class_exists($lifecycleClass)) {
            return; // 无 lifecycle 是合法的
        }
        $instance = new $lifecycleClass();
        if (!$instance instanceof LifecycleHook) {
            throw BusinessException::pluginRuntimeError(
                (string) ($manifest['code'] ?? '?'),
                $hook,
                new \RuntimeException('lifecycle 类未实现 LifecycleHook 接口')
            );
        }
        try {
            $instance->{$hook}();
        } catch (\Throwable $e) {
            throw BusinessException::pluginRuntimeError(
                (string) ($manifest['code'] ?? '?'),
                $hook,
                $e
            );
        }
    }

    /**
     * v2.28.0：软卸载前快照 database/ + plugin.json 到 runtime/plugin-graveyard/<id>/，
     * 供后续 PluginPurgeService 跑 uninstall.sql 用。
     * PluginSqlRunner 仅依赖 <base>/database/ 子目录（含 updates/），plugin.json 用于审计/排查。
     */
    private function snapshotDatabaseToGraveyard(string $pluginDir, int $pluginId): void
    {
        $srcDatabase = $pluginDir . '/database';

        $graveyard = App::getRootPath() . 'runtime/plugin-graveyard/' . $pluginId;
        if (is_dir($graveyard)) {
            // 同 id 不可能重复软卸载（pluginRepo->delete 写 deleted_at 唯一一次），
            // 这里只是防御性兜底：清掉旧残留
            $this->rrmdir($graveyard);
        }
        if (!@mkdir($graveyard, 0o755, true) && !is_dir($graveyard)) {
            error_log("[plugin:{$pluginId}] graveyard mkdir failed: {$graveyard}");
            return;
        }

        // 复制 database/（若存在），递归含 updates/ 子目录
        if (is_dir($srcDatabase)) {
            $this->copyDirRecursive($srcDatabase, $graveyard . '/database');
        }

        // 复制 plugin.json（仅审计/排查用）
        $srcManifest = $pluginDir . '/plugin.json';
        if (is_file($srcManifest)) {
            @copy($srcManifest, $graveyard . '/plugin.json');
        }
    }

    private function copyDirRecursive(string $src, string $dest): void
    {
        @mkdir($dest, 0o755, true);
        foreach (array_diff(scandir($src) ?: [], ['.', '..']) as $f) {
            $sp = $src . '/' . $f;
            $dp = $dest . '/' . $f;
            if (is_dir($sp)) {
                $this->copyDirRecursive($sp, $dp);
            } else {
                @copy($sp, $dp);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = "$dir/$f";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
