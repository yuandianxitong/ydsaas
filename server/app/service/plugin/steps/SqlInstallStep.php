<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin\steps;

use core\base\Service;
use core\plugin\PluginSqlRunner;
use core\saga\CompensatableStep;

/**
 * v2.28.0：执行插件 database/install.sql。
 *
 * - execute：runner->install（状态表自动写 success 行；DDL 隐式提交，不包事务）
 * - compensate：runner->uninstallData —— 执行作者声明的 uninstall.sql 清理并清空状态行
 */
class SqlInstallStep extends Service implements CompensatableStep
{
    public function __construct(
        private string $pluginDir,
        private int $pluginId,
        private string $pluginCode,
        private string $pluginVersion,
        private PluginSqlRunner $runner,
    ) {
    }

    public function name(): string
    {
        return 'SqlInstall';
    }

    public function execute(): void
    {
        $this->runner->install($this->pluginDir, $this->pluginId, $this->pluginCode, $this->pluginVersion);
    }

    public function compensate(): void
    {
        // 失败抛出由 SagaRunner 统一捕获记日志，不影响其它 step 的补偿
        $this->runner->uninstallData($this->pluginDir, $this->pluginId, $this->pluginCode, $this->pluginVersion);
    }
}
