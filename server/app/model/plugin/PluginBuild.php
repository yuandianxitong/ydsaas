<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use think\Model;

class PluginBuild extends Model
{
    protected $name = 'plugin_builds';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = false;

    // 状态常量
    public const STATUS_QUEUED   = 0;
    public const STATUS_RUNNING  = 1;
    public const STATUS_SUCCESS  = 2;
    public const STATUS_FAILED   = 3;
    public const STATUS_ROLLED_BACK = 4;

    // 目标
    public const TARGET_TENANT   = 'tenant';
    public const TARGET_PLATFORM = 'platform';
}
