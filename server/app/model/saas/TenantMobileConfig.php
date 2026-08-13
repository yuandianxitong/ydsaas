<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class TenantMobileConfig extends Model
{
    protected $name = 'tenant_mobile_configs';
    protected $autoWriteTimestamp = 'datetime';

    /** 本表无 deleted_at 列，关闭软删 trait 的默认 scope。 */
    protected $deleteTime = false;

    protected $type = [
        'tabbar_json' => 'json',
    ];
}
