<?php

declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class TenantPcConfig extends Model
{
    protected $name = 'tenant_pc_configs';
    protected $autoWriteTimestamp = 'datetime';

    /** 本表无 deleted_at 列，关闭软删 trait 的默认 scope。 */
    protected $deleteTime = false;

    protected $type = [
        'nav_json' => 'json',
        'seo_json' => 'json',
    ];
}
