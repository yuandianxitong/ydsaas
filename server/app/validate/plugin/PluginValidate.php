<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\validate\plugin;

use think\Validate;

class PluginValidate extends Validate
{
    protected $rule = [
        'plugin'    => 'require|file|fileExt:zip|fileSize:52428800',
        'plugin_id' => 'require|integer|gt:0',
    ];

    protected $message = [
        'plugin.require'   => '请上传插件 zip',
        'plugin.fileExt'   => '只接受 zip 格式',
        'plugin.fileSize'  => 'zip 不能超过 50MB',
        'plugin_id.require' => '插件 ID 不能为空',
    ];

    protected $scene = [
        'upload'    => ['plugin'],
        'install'   => ['plugin_id'],
        'uninstall' => ['plugin_id'],
    ];
}
