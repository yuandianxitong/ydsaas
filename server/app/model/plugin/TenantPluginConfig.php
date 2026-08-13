<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use think\Model;

class TenantPluginConfig extends Model
{
    protected $name = 'tenant_plugin_configs';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
}
