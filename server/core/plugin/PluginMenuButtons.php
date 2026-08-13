<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\plugin;

use think\facade\Db;

/**
 * 插件菜单的操作权限按钮（type=3 节点）同步。
 *
 * 背景：租户端权限校验只认 menus.permission（core\auth\Permission::getRolePermissions
 * 从 role_menus ⨝ menus 取 m.permission，type IN (2,3) 且 permission<>''）。因此插件要让
 * 非超管角色能被授权某项操作，必须把权限码落到菜单上——页面菜单(type=2)承载列表/查看权限，
 * 更细的增删改等操作用无 path 的 type=3 按钮节点承载（与内置模块 MenuSeeder 的按钮约定一致）。
 *
 * plugin.json 菜单声明格式（页面菜单可带）：
 *   { "code": "cms.content", "path": "...", "permission": "cms.content.list",
 *     "buttons": [ { "permission": "cms.content.create", "title": "新建内容" }, ... ] }
 *
 * 幂等匹配：按钮 code 为 NULL（同内置），按 (tenant_id, parent_id, permission) 判重，
 * 可安全重复执行（install/upgrade/enable 都会重跑 syncMenus）。
 */
final class PluginMenuButtons
{
    /**
     * 为某个页面菜单同步其操作按钮节点。
     *
     * @param array<int, array<string, mixed>> $buttons plugin.json 菜单的 buttons 声明
     * @param array<int, int>                  $touched 收集本次涉及的菜单 id（供调用方统一授权给超管角色）
     */
    public static function sync(int $tenantId, int $parentMenuId, array $buttons, int $pluginId, array &$touched): void
    {
        if ($buttons === [] || $parentMenuId <= 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach ($buttons as $btn) {
            $permission = (string) ($btn['permission'] ?? '');
            if ($permission === '') {
                continue;
            }
            $title = (string) ($btn['title'] ?? $btn['name'] ?? $permission);
            $existing = (int) Db::table('menus')
                ->where('tenant_id', $tenantId)
                ->where('parent_id', $parentMenuId)
                ->where('permission', $permission)
                ->value('id');
            $payload = [
                'tenant_id'  => $tenantId,
                'parent_id'  => $parentMenuId,
                'title'      => $title,
                'permission' => $permission,
                'type'       => 3,
                'status'     => 1,
                'updated_at' => $now,
            ];
            if ($existing > 0) {
                Db::table('menus')->where('id', $existing)->update($payload);
                $menuId = $existing;
            } else {
                $payload['created_at'] = $now;
                $menuId = (int) Db::table('menus')->insertGetId($payload);
            }
            $touched[] = $menuId;
            if ($tenantId === 0 && $pluginId > 0
                && !Db::table('plugin_menus')->where('plugin_id', $pluginId)->where('menu_id', $menuId)->count()) {
                Db::table('plugin_menus')->insert(['plugin_id' => $pluginId, 'menu_id' => $menuId, 'sort' => 0, 'created_at' => $now]);
            }
        }
    }
}
