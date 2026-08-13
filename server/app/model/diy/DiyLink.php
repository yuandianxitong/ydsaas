<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;

class DiyLink extends Model
{
    protected $name = 'diy_links';

    protected $type = [
        'tenant_id' => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];
}
