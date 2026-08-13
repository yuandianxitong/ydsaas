<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\PluginMigrationLog;
use core\base\Repository;
use think\Model;

/**
 * v2.7.0：插件 migration 状态查询/写入。
 *
 * 设计要点：
 * - 同名 migration 同方向只能有一条 success（uniq 索引 (plugin_id, name, direction, status)），
 *   失败行可与成功行并存（便于审计）
 * - markSuccess() 前会清理掉同 plugin+name+direction 的 failed 行，避免历史失败行干扰判断
 * - 所有方法都不带 tenant 隔离（平台级表）
 */
class PluginMigrationLogRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PluginMigrationLog();
    }

    /**
     * 已成功执行 up 且仍 live（未被 down 抵消）的 migration 名列表。
     *
     * v2.7.1：表语义改为「当前状态」—— 每条 migration 最多 1 个 success 行
     * （markSuccess 写新方向 success 时清掉旧的反向 success）。
     * 所以这里直接查 direction=up & status=success 即可，无需 array_diff。
     *
     * @return string[]
     */
    public function successfulUpNames(int $pluginId): array
    {
        return $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->where('direction', PluginMigrationLog::DIRECTION_UP)
            ->where('status', PluginMigrationLog::STATUS_SUCCESS)
            ->column('migration_name');
    }

    /**
     * 查 migration 文件的 file_hash（取 up 成功记录），用于 verifyHashes 校验文件是否被改动。
     * @return array<string, string> name => hash
     */
    public function upHashes(int $pluginId): array
    {
        $rows = $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->where('direction', PluginMigrationLog::DIRECTION_UP)
            ->where('status', PluginMigrationLog::STATUS_SUCCESS)
            ->field('migration_name, file_hash')
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['migration_name']] = (string) $r['file_hash'];
        }
        return $out;
    }

    /**
     * v2.7.1：写入 success 前的清理顺序：
     *   1) 删 (plugin, name) 的所有 success 行 —— 含反方向旧 success（up→down 切换或 down→up 重跑都靠这步）
     *   2) 删 (plugin, name, direction) 的旧 failed 行 —— 同方向上次失败的审计行
     *   3) INSERT 新的 success 行
     * 这样唯一键 (plugin_id, name, direction, status) 不会冲突，且 successfulUpNames 简化为单查询。
     */
    public function markSuccess(
        int $pluginId,
        string $pluginCode,
        string $migrationName,
        string $fileHash,
        string $direction,
        ?string $pluginVersion,
        int $durationMs
    ): void {
        // 每个独立操作都用 fresh getModel()->db()，避免 where 条件在链上累积
        $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->where('migration_name', $migrationName)
            ->where('status', PluginMigrationLog::STATUS_SUCCESS)
            ->delete();
        $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->where('migration_name', $migrationName)
            ->where('direction', $direction)
            ->where('status', PluginMigrationLog::STATUS_FAILED)
            ->delete();

        $this->getModel()->db()->insert([
            'plugin_id'      => $pluginId,
            'plugin_code'    => $pluginCode,
            'migration_name' => $migrationName,
            'file_hash'      => $fileHash,
            'direction'      => $direction,
            'plugin_version' => $pluginVersion,
            'duration_ms'    => $durationMs,
            'status'         => PluginMigrationLog::STATUS_SUCCESS,
            'error_msg'      => null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * v2.7.1：markFailed 也要先删同方向旧 failed，否则唯一键 (plugin,name,direction,failed)
     * 会冲突（同一条 migration 第二次失败时）。
     */
    public function markFailed(
        int $pluginId,
        string $pluginCode,
        string $migrationName,
        string $fileHash,
        string $direction,
        ?string $pluginVersion,
        int $durationMs,
        string $errorMsg
    ): void {
        $this->getModel()->db()
            ->where('plugin_id', $pluginId)
            ->where('migration_name', $migrationName)
            ->where('direction', $direction)
            ->where('status', PluginMigrationLog::STATUS_FAILED)
            ->delete();

        $this->getModel()->db()->insert([
            'plugin_id'      => $pluginId,
            'plugin_code'    => $pluginCode,
            'migration_name' => $migrationName,
            'file_hash'      => $fileHash,
            'direction'      => $direction,
            'plugin_version' => $pluginVersion,
            'duration_ms'    => $durationMs,
            'status'         => PluginMigrationLog::STATUS_FAILED,
            'error_msg'      => substr($errorMsg, 0, 1024),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /** purge() 时调用：物理删插件所有 migration 历史 */
    public function purgeForPlugin(int $pluginId): void
    {
        $this->getModel()->db()->where('plugin_id', $pluginId)->delete();
    }
}
