<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use think\Model;

class TenantPlugin extends Model
{
    protected $name = 'tenant_plugins';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    // 状态常量
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_DISABLED = 2;
    public const STATUS_EXPIRED  = 3;

    // 来源
    public const SOURCE_PLAN     = 1;
    public const SOURCE_PURCHASE = 2;
}
