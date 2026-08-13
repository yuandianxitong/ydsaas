<?php
declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;

class DiyPageVersion extends Model
{
    protected $name = 'diy_page_versions';

    // 追加写、无 updated_at / 无软删
    protected $updateTime = false;
    protected $deleteTime = false;

    protected $json = ['components', 'page_settings'];
    protected $jsonAssoc = true;

    protected $type = ['tenant_id' => 'integer', 'page_id' => 'integer', 'version_no' => 'integer'];
}
