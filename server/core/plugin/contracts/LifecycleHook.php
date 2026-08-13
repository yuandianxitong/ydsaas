<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin\contracts;

/**
 * 插件生命周期钩子。
 * 每个插件可选地实现该接口，由 PluginManager 在对应时点调用。
 * 抛出异常会被 PluginManager 转为 BusinessException::pluginRuntimeError()。
 */
interface LifecycleHook
{
    /**
     * 首次安装：跑完 migration 后调用，写入默认数据。
     */
    public function install(): void;

    /**
     * 卸载前：仅做注册信息清理。
     * 实际数据保留，由独立的"清理数据"动作（migrator::down）负责。
     */
    public function uninstall(): void;

    /**
     * 原位升级：在新版本代码就位、新 migration 跑完后调用。
     */
    public function upgrade(string $from, string $to): void;

    /**
     * 租户启用：初始化租户级数据。
     */
    public function enable(int $tenantId): void;

    /**
     * 租户禁用：可选清理租户级缓存等。
     */
    public function disable(int $tenantId): void;
}
