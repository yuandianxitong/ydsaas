<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin\steps;

use app\model\plugin\Plugin;
use app\repository\plugin\PluginGrantRepository;
use core\plugin\AppMenuInstaller;
use core\saga\CompensatableStep;

/**
 * v2.7.0：App 插件 → 写菜单模板 + 复制到已授权租户。
 *
 * - execute：installMenuTemplates(pluginId, manifest.tenant.menus) + copyToTenant for each granted
 * - compensate：只回滚本次 execute 的执行清单（新建的删掉、收养的改绑回旧 plugin_id），
 *   不会像早期实现那样 removeForPlugin 一锅端——那会把收养来的、其他租户仍在用的历史菜单
 *   连同 role_menus 一起物理删掉（跨租户数据丢失）。
 *
 * 非 App 插件（kind=plugin）什么都不做。
 */
class InstallMenusStep implements CompensatableStep
{
    /** @var array{created: int[], adopted: array<int, int>}|null execute 成功后的执行清单；未执行或无需处理时为 null */
    private ?array $installResult = null;

    /** @param array<string, mixed> $manifest */
    public function __construct(
        private int $pluginId,
        private string $kind,
        private array $manifest,
        private AppMenuInstaller $appMenuInstaller,
        private PluginGrantRepository $grantRepo,
    ) {
    }

    public function name(): string
    {
        return 'InstallMenus';
    }

    public function execute(): void
    {
        if ($this->kind !== Plugin::KIND_APP) {
            return;
        }
        $menus = (array) ($this->manifest['tenant']['menus'] ?? []);
        if (empty($menus)) {
            return;
        }
        $pluginCode = (string) ($this->manifest['code'] ?? '');
        $this->installResult = $this->appMenuInstaller->installMenuTemplates($this->pluginId, $pluginCode, $menus);
        foreach ($this->grantRepo->listGrantedTenantIds($this->pluginId) as $tid) {
            $this->appMenuInstaller->copyToTenant($this->pluginId, (int) $tid);
        }
    }

    public function compensate(): void
    {
        if ($this->kind !== Plugin::KIND_APP || $this->installResult === null) {
            return;
        }
        $this->appMenuInstaller->rollbackInstall($this->pluginId, $this->installResult);
    }
}
