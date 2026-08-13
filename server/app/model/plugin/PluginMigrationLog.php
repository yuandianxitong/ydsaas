<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use think\Model;

/**
 * v2.7.0：插件 SQL 执行状态表（v2.28.0 起由 PluginSqlRunner 维护）。
 * up/down 每跑过一个 SQL 文件就写一行，PluginSqlRunner 据此跳过已跑、
 * 检测 file_hash 漂移、记录耗时与错误。
 */
class PluginMigrationLog extends Model
{
    protected $name = 'plugin_migrations';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    public const DIRECTION_UP   = 'up';
    public const DIRECTION_DOWN = 'down';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';
}
