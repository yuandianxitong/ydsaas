<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\validate\diy;

use think\Validate;

class DiyLinkValidate extends Validate
{
    protected $rule = [
        'label' => 'require|max:64',
        'path'  => 'require|max:255',
    ];

    protected $scene = [
        'create' => ['label', 'path'],
        'update' => ['label', 'path'],
    ];
}
