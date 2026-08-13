<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\PluginMenu;
use core\base\Repository;
use think\Model;

class PluginMenuRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PluginMenu();
    }

    /**
     * 返回某插件关联的全部 menu_id 列表。
     *
     * @return array<int, int>
     */
    public function listMenuIdsByPlugin(int $pluginId): array
    {
        return array_column(
            $this->getModel()->db()->where('plugin_id', $pluginId)->field('menu_id')->select()->toArray(),
            'menu_id'
        );
    }
}
