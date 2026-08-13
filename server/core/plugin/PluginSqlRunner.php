<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\plugin;

use app\repository\plugin\PluginMigrationLogRepository;
use core\exception\BusinessException;
use think\facade\Config;
use think\facade\Db;

/**
 * 插件 SQL 执行器（v2.28.0，替代旧版基于 PHP 类的插件迁移机制）。
 *
 * 目录约定 <pluginDir>/database/：
 *   install.sql   全新安装（始终反映最新完整结构）
 *   uninstall.sql 清理数据（purge / saga 补偿）
 *   testdata.sql  演示数据（仅 DML，{TENANT_ID} 占位）
 *   updates/vX.Y.Z.sql 增量升级
 *
 * 状态表沿用 plugin_migrations：migration 列记文件相对路径；
 * 已 success 的文件不重跑；hash 防篡改语义沿用旧版迁移机制的设计
 * （config saas.plugin_migration_strict_hash）。
 */
class PluginSqlRunner
{
    private PluginMigrationLogRepository $logRepo;

    public function __construct(?PluginMigrationLogRepository $logRepo = null)
    {
        $this->logRepo = $logRepo ?? app()->make(PluginMigrationLogRepository::class);
    }

    /** @return string[] 本次执行的文件相对路径 */
    public function install(string $pluginDir, int $pluginId, string $pluginCode, ?string $pluginVersion = null): array
    {
        $this->verifyHashes($pluginDir, $pluginId);

        $files   = $this->listSqlFiles($pluginDir);
        $applied = $this->logRepo->successfulUpNames($pluginId);

        $ran = [];
        foreach ($files as $rel => $abs) {
            if (in_array($rel, $applied, true)) {
                continue;
            }
            // 全新安装只跑 install.sql；updates/ 是历史增量，新装环境结构已含在 install.sql 里，
            // 直接标记为已应用（backfill），避免升级时重复执行。
            if ($rel === 'database/install.sql') {
                $this->runFile($abs, $rel, $pluginId, $pluginCode, $pluginVersion);
                $ran[] = $rel;
            } else {
                $hash = hash_file('sha256', $abs) ?: '';
                $this->logRepo->markSuccess($pluginId, $pluginCode, $rel, $hash, 'up', $pluginVersion, 0);
            }
        }
        return $ran;
    }

    /** @return string[] 本次执行的文件相对路径（按版本升序） */
    public function upgrade(string $pluginDir, int $pluginId, string $pluginCode, string $fromVersion, string $toVersion): array
    {
        $this->verifyHashes($pluginDir, $pluginId);

        $files   = $this->listSqlFiles($pluginDir);
        $applied = $this->logRepo->successfulUpNames($pluginId);

        $ran = [];
        foreach ($files as $rel => $abs) {
            if ($rel === 'database/install.sql' || in_array($rel, $applied, true)) {
                continue;
            }
            $ver = self::versionOf($abs);
            if (version_compare($ver, $fromVersion, '<=') || version_compare($ver, $toVersion, '>')) {
                continue;
            }
            $this->runFile($abs, $rel, $pluginId, $pluginCode, $toVersion);
            $ran[] = $rel;
        }
        return $ran;
    }

    public function hasTestdata(string $pluginDir): bool
    {
        return is_file(rtrim($pluginDir, '/') . '/database/testdata.sql');
    }

    /**
     * 导入演示数据：{TENANT_ID} → 租户 ID；仅 DML（INSERT/UPDATE）；
     * 事务包裹——失败整体回滚，重试不会产生重复行。
     *
     * @return int 执行语句数
     */
    public function importTestdata(string $pluginDir, int $tenantId): int
    {
        $file = rtrim($pluginDir, '/') . '/database/testdata.sql';
        if (!is_file($file)) {
            throw new BusinessException('插件不包含演示数据（database/testdata.sql）', 404);
        }

        $sql = str_replace('{TENANT_ID}', (string) $tenantId, (string) file_get_contents($file));
        $stmts = SqlScript::split($sql);
        foreach ($stmts as $stmt) {
            if (!preg_match('/^(INSERT|UPDATE)\b/i', $stmt)) {
                throw new BusinessException('testdata.sql 仅允许 INSERT/UPDATE 语句', 422);
            }
        }

        Db::transaction(static function () use ($stmts) {
            foreach ($stmts as $stmt) {
                Db::execute($stmt);
            }
        });
        return count($stmts);
    }

    /** 执行 uninstall.sql（如有）并清空状态行。@return bool 是否执行了 uninstall.sql */
    public function uninstallData(string $pluginDir, int $pluginId, string $pluginCode, ?string $pluginVersion = null): bool
    {
        $file = rtrim($pluginDir, '/') . '/database/uninstall.sql';
        $ran = false;
        if (is_file($file)) {
            foreach (SqlScript::split((string) file_get_contents($file)) as $stmt) {
                Db::execute($stmt);
            }
            $ran = true;
        }
        $this->logRepo->purgeForPlugin($pluginId);
        return $ran;
    }

    /**
     * 校验 SQL 文件 hash 是否与执行记录一致，检测「已执行文件被篡改」。
     *
     * database/install.sql 被显式排除在校验范围外：spec 要求它必须随每个含 schema
     * 变更的版本更新为最新完整结构（见类 docblock），因此它的内容会合法地持续演进；
     * 而它只在 install() 时执行一次并记录 hash，upgrade() 永远不会重跑它、也不会重
     * 新记录 hash。若纳入校验，每次正常的版本升级都会被误判为「hash 漂移」——
     * 默认模式产生噪音 error_log，strict 模式下更会直接 409 阻断升级。install.sql
     * 在 success 之后不存在重跑执行面，篡改它没有实际风险，故豁免。
     *
     * @return array<int, array{name: string, recorded: string, current: string}>
     */
    public function verifyHashes(string $pluginDir, int $pluginId): array
    {
        $files    = $this->listSqlFiles($pluginDir);
        $recorded = $this->logRepo->upHashes($pluginId);

        $mismatch = [];
        foreach ($files as $rel => $abs) {
            if ($rel === 'database/install.sql' || !isset($recorded[$rel])) {
                continue;
            }
            $current = hash_file('sha256', $abs) ?: '';
            if ($current !== $recorded[$rel]) {
                $mismatch[] = ['name' => $rel, 'recorded' => $recorded[$rel], 'current' => $current];
            }
        }

        if (!empty($mismatch)) {
            $names = implode(', ', array_column($mismatch, 'name'));
            $msg = "plugin {$pluginId} SQL 文件 hash 漂移：{$names}";
            if (Config::get('saas.plugin_migration_strict_hash', false)) {
                throw new BusinessException($msg . '（已开启 strict 模式，拒绝继续）', 409);
            }
            error_log('[PluginSqlRunner] ' . $msg);
        }
        return $mismatch;
    }

    /** @return string[] 未执行的文件相对路径 */
    public function pendingUp(string $pluginDir, int $pluginId): array
    {
        $files   = array_keys($this->listSqlFiles($pluginDir));
        $applied = $this->logRepo->successfulUpNames($pluginId);
        return array_values(array_diff($files, $applied));
    }

    /** 单文件执行：分句 → 逐句 execute → 记状态（成功/失败） */
    private function runFile(string $abs, string $rel, int $pluginId, string $pluginCode, ?string $pluginVersion): void
    {
        $hash = hash_file('sha256', $abs) ?: '';
        $t0 = (int) (microtime(true) * 1000);
        try {
            foreach (SqlScript::split((string) file_get_contents($abs)) as $stmt) {
                Db::execute($stmt);
            }
            $duration = ((int) (microtime(true) * 1000)) - $t0;
            $this->logRepo->markSuccess($pluginId, $pluginCode, $rel, $hash, 'up', $pluginVersion, $duration);
        } catch (\Throwable $e) {
            $duration = ((int) (microtime(true) * 1000)) - $t0;
            $this->logRepo->markFailed($pluginId, $pluginCode, $rel, $hash, 'up', $pluginVersion, $duration, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array<string, string> 相对路径 => 绝对路径；install.sql 恒排首位，
     *                               updates/ 按 version_compare 升序
     */
    private function listSqlFiles(string $pluginDir): array
    {
        $base = rtrim($pluginDir, '/') . '/database';
        $out = [];
        if (is_file($base . '/install.sql')) {
            $out['database/install.sql'] = $base . '/install.sql';
        }
        $updates = glob($base . '/updates/v*.sql') ?: [];
        usort($updates, static fn (string $a, string $b) =>
            version_compare(self::versionOf($a), self::versionOf($b)));
        foreach ($updates as $file) {
            $out['database/updates/' . basename($file)] = $file;
        }
        return $out;
    }

    private static function versionOf(string $file): string
    {
        return preg_match('/^v(\d+\.\d+\.\d+)\.sql$/', basename($file), $m) ? $m[1] : '0.0.0';
    }
}
