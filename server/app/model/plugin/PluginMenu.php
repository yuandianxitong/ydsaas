<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use core\base\Model;

class PluginMenu extends Model
{
    protected $name = 'plugin_menus';
    protected $autoWriteTimestamp = 'datetime';
    protected $updateTime = false;
}
