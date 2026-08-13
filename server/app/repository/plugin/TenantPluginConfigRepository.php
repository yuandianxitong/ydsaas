<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\TenantPluginConfig;
use core\base\Repository;
use think\Model;

class TenantPluginConfigRepository extends Repository
{
    protected function getModel(): Model
    {
        return new TenantPluginConfig();
    }

    /**
     * 读取某租户某插件的全部配置 K-V
     *
     * @return array<string, string|null>
     */
    public function loadConfigs(int $tenantId, string $pluginCode): array
    {
        $rows = $this->getModel()->db()
            ->where('tenant_id', $tenantId)
            ->where('plugin_code', $pluginCode)
            ->select()
            ->toArray();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['config_key']] = $r['config_value'];
        }
        return $out;
    }

    /**
     * 批量替换某租户某插件的全部配置 K-V。
     * 简单实现：delete-then-insertAll，事务由调用方管理。
     *
     * @param array<string, string|null> $kv
     */
    public function upsert(int $tenantId, string $pluginCode, array $kv): void
    {
        $model = $this->getModel();
        $model->db()
            ->where('tenant_id', $tenantId)
            ->where('plugin_code', $pluginCode)
            ->delete();

        if ($kv === []) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach ($kv as $k => $v) {
            $rows[] = [
                'tenant_id'    => $tenantId,
                'plugin_code'  => $pluginCode,
                'config_key'   => (string) $k,
                'config_value' => $v === null ? null : (string) $v,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        $model->db()->insertAll($rows);
    }
}
