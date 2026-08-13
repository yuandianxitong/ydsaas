<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use core\base\Model;

class Plugin extends Model
{
    protected $name = 'plugins';

    // 时间戳与软删除字段全部来自 core\base\Model
    // 但本表 autoWriteTimestamp 使用 datetime 字符串而非默认 true（int），保持与 Stage 1 schema 一致
    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'manifest' => 'json',
        'requires' => 'json',
    ];

    // 状态常量
    public const STATUS_ENABLED     = 1;
    public const STATUS_DISABLED    = 2;
    public const STATUS_INSTALLING  = 3;
    public const STATUS_UPGRADING   = 4;
    public const STATUS_FAILED      = 5;
    /** 已卸载：保留代码目录与业务表，可二次安装；清理数据走 purge */
    public const STATUS_UNINSTALLED = 6;

    // 类型常量（与 plan_features.type 对齐）
    public const TYPE_BUSINESS = 1;
    public const TYPE_NOTIFY   = 2;
    public const TYPE_PAYMENT  = 3;
    public const TYPE_ADDON    = 4;

    // 来源常量
    public const SOURCE_ZIP      = 1;
    public const SOURCE_BUILTIN  = 2;

    // 类别常量（App 贡献顶级菜单；Plugin 不贡献）
    public const KIND_APP    = 'app';
    public const KIND_PLUGIN = 'plugin';
}
