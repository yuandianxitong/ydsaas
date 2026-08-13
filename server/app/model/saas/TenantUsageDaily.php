<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

/**
 * TenantUsageDaily 租户用量快照
 *
 * append-only 表，只有 created_at，没有 updated_at / deleted_at。
 * 禁用 updateTime 和 SoftDelete。
 */
class TenantUsageDaily extends Model
{
    protected $name = 'tenant_usage_daily';
    protected $autoWriteTimestamp = 'datetime';

    // 只自动写 created_at，不写 updated_at
    protected $updateTime = false;

    // 没有 deleted_at 列
    protected $deleteTime = false;
    protected $hidden = [];
}
