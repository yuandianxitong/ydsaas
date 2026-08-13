<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;

class DiyPage extends Model
{
    protected $name = 'diy_pages';

    protected $json = ['components_draft', 'components_published', 'page_settings'];
    protected $jsonAssoc = true;

    protected $type = [
        'tenant_id' => 'integer',
        'status'    => 'integer',
    ];
}
