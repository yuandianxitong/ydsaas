<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\validate\v1;

use think\Validate;

class PluginValidate extends Validate
{
    protected $rule = [
        'plugin_id' => 'require|integer|gt:0',
    ];

    protected $scene = [
        'enable'  => ['plugin_id'],
        'disable' => ['plugin_id'],
    ];
}
