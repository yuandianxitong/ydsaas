<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

class Plan extends Model
{
    protected $name = 'plans';
    protected $autoWriteTimestamp = 'datetime';
}
