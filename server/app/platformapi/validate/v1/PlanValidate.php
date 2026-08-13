<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\validate\v1;

use core\base\Validate;

class PlanValidate extends Validate
{
    protected $rule = [
        'code'                => 'require|alphaDash|length:2,50',
        'name'                => 'require|length:2,100',
        'description'         => 'max:1000',
        'price_monthly'       => 'float|egt:0',
        'price_yearly'        => 'float|egt:0',
        'storage_limit_bytes' => 'integer|egt:0',
        'sort'                => 'integer',
        'status'              => 'in:0,1',
    ];

    protected $message = [
        'code.require'   => 'validation.plan_code_required',
        'code.alphaDash' => 'validation.plan_code_alpha_dash',
        'code.length'    => 'validation.plan_code_length',
        'name.require'   => 'validation.plan_name_required',
    ];

    protected $scene = [
        'create' => ['code', 'name', 'description', 'price_monthly', 'price_yearly', 'storage_limit_bytes', 'sort', 'status'],
        'update' => ['name', 'description', 'price_monthly', 'price_yearly', 'storage_limit_bytes', 'sort', 'status'],
    ];
}
