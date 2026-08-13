<?php
declare(strict_types=1);

namespace app\repository\saas;

use app\model\system\SystemConfig;
use core\base\Repository;
use think\Model;

class PlatformConfigRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new SystemConfig();
    }

    /**
     * 按分组获取平台配置列表
     */
    public function getByGroup(string $group): array
    {
        return $this->getModel()->db()
            ->where('tenant_id', 0)
            ->where('config_group', $group)
            ->where('config_key', 'like', 'platform_%')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 按 ID 获取单条平台配置
     */
    public function findPlatformConfig(int $id): ?array
    {
        $row = $this->getModel()->db()
            ->where('id', $id)
            ->where('tenant_id', 0)
            ->where('config_key', 'like', 'platform_%')
            ->find();
        return $row ?: null;
    }

    /**
     * 更新配置值
     */
    public function updateValue(int $id, string $value): bool
    {
        return $this->getModel()->db()
            ->where('id', $id)
            ->where('tenant_id', 0)
            ->where('config_key', 'like', 'platform_%')
            ->update([
                'config_value' => $value,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]) !== false;
    }

    /**
     * 按 key 批量更新配置值
     */
    public function updateByKey(string $key, string $value): bool
    {
        return $this->getModel()->db()
            ->where('tenant_id', 0)
            ->where('config_key', $key)
            ->update([
                'config_value' => $value,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]) !== false;
    }

    /**
     * 获取所有有效的平台配置（key-value 形式）
     */
    public function getAllActive(): array
    {
        return $this->getModel()->db()
            ->where('tenant_id', 0)
            ->where('config_key', 'like', 'platform_%')
            ->where('status', 1)
            ->select()
            ->toArray();
    }

    /**
     * 按 key 获取单条有效配置
     */
    public function findByKey(string $key): ?array
    {
        $row = $this->getModel()->db()
            ->where('tenant_id', 0)
            ->where('config_key', $key)
            ->where('status', 1)
            ->find();
        return $this->toRow($row);
    }

    /**
     * 按 key 获取单条配置（忽略 status，用于读取/兜底创建内部配置）
     */
    public function findByKeyAny(string $key): ?array
    {
        $row = $this->getModel()->db()
            ->where('tenant_id', 0)
            ->where('config_key', $key)
            ->find();
        return $this->toRow($row);
    }

    /**
     * find() 在不同驱动下可能返回 Model 或 array，统一成 ?array
     */
    private function toRow(mixed $row): ?array
    {
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * 创建一条平台配置（tenant_id=0）
     */
    public function createConfig(array $data): array
    {
        $data['tenant_id'] = 0;
        return $this->create($data);
    }
}
