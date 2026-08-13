<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin;

use app\repository\plugin\PluginRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\AppMenuInstaller;
use core\plugin\PluginSqlRunner;
use think\facade\App;
use think\facade\Db;

/**
 * 物理清理插件数据：仅可在已卸载（status=UNINSTALLED 或历史 deleted_at）上执行。
 * 执行 database/uninstall.sql 清理业务表 + 删除代码目录 + 物理删 plugins 行。
 */
class PluginPurgeService extends Service
{
    protected PluginRepository $pluginRepo;
    protected PluginSqlRunner $sqlRunner;
    protected AppMenuInstaller $appMenuInstaller;

    /**
     * @return array<string, mixed>
     */
    public function purge(int $pluginId, bool $force = false): array
    {
        // 注意：直接查 plugins 表（含软删除行）
        $row = Db::table('plugins')->where('id', $pluginId)->find();
        if (!$row) {
            throw new BusinessException('插件不存在', 404);
        }
        $status = (int) ($row['status'] ?? 0);
        $softDeleted = !empty($row['deleted_at']);
        if ($status !== \app\model\plugin\Plugin::STATUS_UNINSTALLED && !$softDeleted) {
            throw new BusinessException('请先卸载插件再清理数据', 409);
        }
        if (!$force) {
            throw new BusinessException('数据清理是不可逆操作，必须传 force=true 确认', 422);
        }

        $code = (string) $row['code'];
        $rootPath = App::getRootPath();
        $pluginDir = $rootPath . 'plugins/' . $code;
        $graveyard = $rootPath . 'runtime/plugin-graveyard/' . $pluginId;

        // 优先用仍保留的代码目录；历史软卸流程回退 graveyard 快照
        $sqlBase = is_dir($pluginDir) ? $pluginDir
            : (is_dir($graveyard) ? $graveyard : null);
        if ($sqlBase !== null) {
            // uninstall.sql 含 DDL（DROP TABLE），事务无意义，直接执行
            $this->sqlRunner->uninstallData($sqlBase, $pluginId, $code, (string) ($row['version'] ?? ''));
        }

        // 物理删除插件贡献的菜单（模板 + 所有租户副本）
        $this->appMenuInstaller->removeForPlugin($pluginId);

        // 清理阶段才删除本地代码目录
        if (is_dir($pluginDir)) {
            $this->rrmdir($pluginDir);
        }

        // 物理删除 plugins 行 + 关联表行
        Db::table('plugins')->where('id', $pluginId)->delete();
        Db::table('plugin_grants')->where('plugin_id', $pluginId)->delete();
        Db::table('tenant_plugins')->where('plugin_id', $pluginId)->delete();
        Db::table('tenant_plugin_configs')->where('plugin_code', $code)->delete();
        Db::table('plugin_builds')->where('plugin_id', $pluginId)->delete();
        // v2.7.0：清理状态表行（包含 success 与 failed 历史）
        Db::table('plugin_migrations')->where('plugin_id', $pluginId)->delete();

        // v2.6.7：清理 graveyard 快照（如有）
        if (is_dir($graveyard)) {
            $this->rrmdir($graveyard);
        }

        return ['id' => $pluginId, 'code' => $code, 'purged' => true];
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
