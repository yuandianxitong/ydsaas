<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

/**
 * SaasOrder 平台订单
 *
 * 有软删除支持（deleted_at 列），继承 core\base\Model 的默认 SoftDelete 行为。
 */
class SaasOrder extends Model
{
    protected $name = 'saas_orders';
    protected $autoWriteTimestamp = 'datetime';
}
