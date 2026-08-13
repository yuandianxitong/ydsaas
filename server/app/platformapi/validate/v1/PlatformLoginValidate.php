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

class PlatformLoginValidate extends Validate
{
    protected $rule = [
        'username' => 'require|length:2,50',
        'password' => 'require|length:6,100',
    ];

    protected $message = [
        'username.require' => 'validation.username_required',
        'username.length'  => 'validation.username_length',
        'password.require' => 'validation.password_required',
        'password.length'  => 'validation.password_length',
    ];
}
