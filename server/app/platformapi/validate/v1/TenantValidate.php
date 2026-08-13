<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\validate\v1;

use core\base\Validate;

class TenantValidate extends Validate
{
    protected $rule = [
        'tenant_code'         => 'require|alphaDash|length:3,50',
        'name'                => 'require|length:2,100',
        'plan_id'             => 'integer|>=:0',
        'storage_limit_bytes' => 'integer|>=:0',
        'contact_name'        => 'length:0,50',
        'contact_phone'       => 'length:0,20',
        'status'              => 'integer|in:0,1',
        'admin_username'      => 'require|alphaNum|length:4,30',
        'admin_password'      => 'require|length:6,30',
        'months'              => 'require|integer|between:1,120',
        'remark'              => 'length:0,255',
        'transaction_id'      => 'length:0,128',
    ];

    protected $message = [
        'tenant_code.require'   => 'validation.tenant_code_required',
        'tenant_code.alphaDash' => 'validation.tenant_code_alpha_dash',
        'tenant_code.length'    => 'validation.tenant_code_length',
        'name.require'          => 'validation.tenant_name_required',
        'name.length'           => 'validation.tenant_name_length',
        'plan_id.integer'       => 'validation.plan_id_integer',
        'plan_id.>='            => 'validation.plan_id_gte',
        'storage_limit_bytes.integer' => 'validation.storage_limit_integer',
        'status.integer'        => 'validation.status_integer',
        'status.in'             => 'validation.status_in',
        'admin_username.require'  => 'validation.admin_username_required',
        'admin_username.alphaNum' => 'validation.admin_username_alpha_num',
        'admin_username.length'   => 'validation.admin_username_length',
        'admin_password.require'  => 'validation.admin_password_required',
        'admin_password.length'   => 'validation.admin_password_length',
        'months.require'          => 'validation.months_required',
        'months.between'          => 'validation.months_between',
    ];

    protected $scene = [
        // opening_mode / trial_days / months 由 TenantService 按模式校验，避免空字段触发 between
        'create' => [
            'tenant_code', 'name', 'plan_id', 'storage_limit_bytes', 'contact_name', 'contact_phone',
            'status', 'admin_username', 'admin_password',
        ],
        'update' => ['name', 'plan_id', 'storage_limit_bytes', 'contact_name', 'contact_phone', 'status'],
        'offlineRenew' => ['plan_id', 'months', 'remark', 'transaction_id'],
    ];
}
