<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\saas;

use core\base\Model;

/**
 * Subscription 订阅记录
 *
 * 追溯性表：一条订阅表示一次套餐生效。不支持软删除，因为历史订阅是
 * 审计必要数据。status 字段表示订阅本身的生命周期（1=生效 2=已结束
 * 3=已退款），而不是"软删除标记"。
 */
class Subscription extends Model
{
    protected $name = 'subscriptions';
    protected $autoWriteTimestamp = 'datetime';

    /**
     * 禁用 SoftDelete —— subscriptions 表没有 deleted_at 列。
     * 同 M2C T65 PlanFeature 的坑：不禁用会触发 column not found。
     */
    protected $deleteTime = false;
    protected $hidden = [];
}
