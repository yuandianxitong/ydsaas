<?php
declare(strict_types=1);

namespace app\repository\message;

use app\model\message\MessageEvent;
use core\base\Repository;
use think\Model;

class MessageEventRepository extends Repository
{
    protected function getModel(): Model
    {
        return new MessageEvent();
    }

    /**
     * 获取指定事件码下已启用且已绑定模板的配置
     */
    public function getEnabledByEventCode(string $eventCode, int $tenantId): array
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->where('event_code', $eventCode)
            ->where('is_enabled', 1)
            ->where('template_id', '>', 0)
            ->select()
            ->toArray();
    }

    /**
     * 获取租户下的所有事件配置
     */
    public function getByTenant(int $tenantId): array
    {
        return $this->query()
            ->where('tenant_id', $tenantId)
            ->select()
            ->toArray();
    }
}
