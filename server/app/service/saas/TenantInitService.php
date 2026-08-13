<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\plugin\AppMenuInstaller;
use think\facade\Db;

/**
 * 租户初始化 Service
 *
 * 在平台管理员创建租户后，将 tenant_id=0 的模板数据复制到新租户，
 * 并创建租户超级管理员账号。
 *
 * 注意：此方法在外层事务中调用，不自行开启/提交事务。
 */
class TenantInitService extends Service
{
    protected AppMenuInstaller $appMenuInstaller;

    /**
     * 初始化新租户的基础数据
     *
     * @param int    $tenantId       新租户 ID
     * @param string $adminUsername  管理员用户名
     * @param string $adminPassword  管理员明文密码
     */
    public function initTenant(int $tenantId, string $adminUsername, string $adminPassword): void
    {
        $now = date('Y-m-d H:i:s');

        $menuMap       = $this->copyMenus($tenantId, $now);
        $roleMap       = $this->copyRoles($tenantId, $now);
        $this->copyRoleMenus($tenantId, $roleMap, $menuMap);
        $permissionMap = $this->copyPermissions($tenantId, $now);
        $this->copyRolePermissions($tenantId, $roleMap, $permissionMap);
        $dictMap       = $this->copyDictionaries($tenantId, $now);
        $this->copyDictionaryItems($tenantId, $dictMap, $now);
        $this->syncMessageTemplates($tenantId);
        $this->copyDepartments($tenantId, $now);
        $this->seedSystemConfigs($tenantId, $now);
        $this->copyDiyPages($tenantId, $now);
        $this->copyPcConfig($tenantId, $now);

        $adminId = $this->createAdmin($tenantId, $adminUsername, $adminPassword, $now);
        $this->createAdminRoles($tenantId, $adminId, $roleMap, $now);

        // 基础数据复制完后，按租户当前 plan 的 grant 把 app 插件菜单挂上来。
        // copyMenus 不再带插件菜单进来，所以这一步是新租户获得插件菜单的唯一入口。
        $this->seedAppMenusByPlan($tenantId);
    }

    /**
     * 复制菜单（自引用树，需要重映射 parent_id）
     *
     * 插件菜单（plugin_menus.menu_id 命中的模板行）不在此复制——这些菜单按
     * 租户当前 plan 的 grant 由 seedAppMenusByPlan 单独复制，避免新租户拿到
     * 套餐没授权的插件菜单。
     *
     * @return array<int,int> oldId → newId
     */
    private function copyMenus(int $tenantId, string $now): array
    {
        $pluginMenuIds = array_map('intval', Db::table('plugin_menus')->column('menu_id'));
        $pluginMenuSet = array_fill_keys($pluginMenuIds, true);

        $rows = Db::table('menus')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $map           = [];
        $oldParentByNew = [];

        // Pass 1：先插入所有菜单（parent_id 暂置 0），建立 old→new 映射并记录原 parent。
        // 不能在插入时直接映射 parent_id——模板按 id 升序复制，若某目录 id 大于其子菜单
        // （如分组目录 200 是叶子 100 的父级），子菜单会先于父目录被处理、映射缺失而被挂到顶级。
        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            if (isset($pluginMenuSet[$oldId])) {
                continue;
            }
            $oldParent = (int) $row['parent_id'];
            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['parent_id']  = 0;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $newId       = Db::table('menus')->insertGetId($row);
            $map[$oldId]  = $newId;
            if ($oldParent !== 0) {
                $oldParentByNew[$newId] = $oldParent;
            }
        }

        // Pass 2：回填 parent_id（与插入顺序无关，所有 id 此时都已在 map 中）。
        foreach ($oldParentByNew as $newId => $oldParent) {
            if (isset($map[$oldParent])) {
                Db::table('menus')->where('id', $newId)->update(['parent_id' => $map[$oldParent]]);
            }
        }

        return $map;
    }

    /**
     * 按租户 plan 的 grant 复制 app 插件菜单。
     *
     * 仅处理已启用（status=1）且 kind=app 的插件。grant 表里其他 kind 的插件
     * 走 panels 协议，不进菜单系统。
     */
    private function seedAppMenusByPlan(int $tenantId): void
    {
        $planId = (int) Db::table('tenants')->where('id', $tenantId)->value('plan_id');
        if ($planId <= 0) {
            return;
        }

        $pluginIds = Db::table('plugin_grants')
            ->alias('pg')
            ->join('plugins p', 'p.id = pg.plugin_id')
            ->where('pg.plan_id', $planId)
            ->where('p.status', 1)
            ->where('p.kind', 'app')
            ->whereNull('p.deleted_at')
            ->column('p.id');

        foreach ($pluginIds as $pid) {
            $this->appMenuInstaller->copyToTenant((int) $pid, $tenantId);
        }
    }

    /**
     * 复制角色
     *
     * @return array<int,int> oldId → newId
     */
    private function copyRoles(int $tenantId, string $now): array
    {
        $rows = Db::table('roles')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $map = [];

        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $newId       = Db::table('roles')->insertGetId($row);
            $map[$oldId] = $newId;
        }

        return $map;
    }

    /**
     * 复制角色-菜单关联（pivot，无 deleted_at）
     *
     * @param array<int,int> $roleMap
     * @param array<int,int> $menuMap
     */
    private function copyRoleMenus(int $tenantId, array $roleMap, array $menuMap): void
    {
        $rows = Db::table('role_menus')
            ->whereIn('role_id', array_keys($roleMap))
            ->select()
            ->toArray();

        $batch = [];

        foreach ($rows as $row) {
            $oldRoleId = (int) $row['role_id'];
            $oldMenuId = (int) $row['menu_id'];

            if (!isset($roleMap[$oldRoleId]) || !isset($menuMap[$oldMenuId])) {
                continue;
            }

            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['role_id']    = $roleMap[$oldRoleId];
            $row['menu_id']    = $menuMap[$oldMenuId];
            $row['created_at'] = date('Y-m-d H:i:s');
            $row['updated_at'] = date('Y-m-d H:i:s');

            $batch[] = $row;
        }

        if (!empty($batch)) {
            Db::table('role_menus')->insertAll($batch);
        }
    }

    /**
     * 复制权限
     *
     * @return array<int,int> oldId → newId
     */
    private function copyPermissions(int $tenantId, string $now): array
    {
        $rows = Db::table('permissions')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $map = [];

        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $newId       = Db::table('permissions')->insertGetId($row);
            $map[$oldId] = $newId;
        }

        return $map;
    }

    /**
     * 复制角色-权限关联（pivot，无 deleted_at）
     *
     * @param array<int,int> $roleMap
     * @param array<int,int> $permissionMap
     */
    private function copyRolePermissions(int $tenantId, array $roleMap, array $permissionMap): void
    {
        $rows = Db::table('role_permissions')
            ->whereIn('role_id', array_keys($roleMap))
            ->select()
            ->toArray();

        $batch = [];

        foreach ($rows as $row) {
            $oldRoleId       = (int) $row['role_id'];
            $oldPermissionId = (int) $row['permission_id'];

            if (!isset($roleMap[$oldRoleId]) || !isset($permissionMap[$oldPermissionId])) {
                continue;
            }

            unset($row['id']);

            $row['tenant_id']     = $tenantId;
            $row['role_id']       = $roleMap[$oldRoleId];
            $row['permission_id'] = $permissionMap[$oldPermissionId];
            $row['created_at']    = date('Y-m-d H:i:s');
            $row['updated_at']    = date('Y-m-d H:i:s');

            $batch[] = $row;
        }

        if (!empty($batch)) {
            Db::table('role_permissions')->insertAll($batch);
        }
    }

    /**
     * 复制字典
     *
     * @return array<int,int> oldId → newId
     */
    private function copyDictionaries(int $tenantId, string $now): array
    {
        $rows = Db::table('dictionaries')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $map = [];

        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $newId       = Db::table('dictionaries')->insertGetId($row);
            $map[$oldId] = $newId;
        }

        return $map;
    }

    /**
     * 复制字典项（重映射 dictionary_id）
     *
     * @param array<int,int> $dictMap
     */
    private function copyDictionaryItems(int $tenantId, array $dictMap, string $now): void
    {
        $rows = Db::table('dictionary_items')
            ->whereIn('dictionary_id', array_keys($dictMap))
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $batch = [];
        foreach ($rows as $row) {
            $oldDictId = (int) $row['dictionary_id'];

            if (!isset($dictMap[$oldDictId])) {
                continue;
            }

            unset($row['id']);

            $row['dictionary_id'] = $dictMap[$oldDictId];
            $row['tenant_id']     = $tenantId;
            $row['created_at']    = $now;
            $row['updated_at']    = $now;

            $batch[] = $row;
        }

        if (!empty($batch)) {
            Db::table('dictionary_items')->insertAll($batch);
        }
    }

    /**
     * 补齐租户消息模板（从 tenant_id=0 模板按 code 幂等补插，只增不改）。
     *
     * initTenant 复制链与 saas:message-template-sync 存量回填命令共用（#6）。
     * 判重不过滤 deleted_at：软删行仍占用 uk_tenant_code 唯一键，补插会撞约束。
     *
     * @return int 补插条数
     */
    public function syncMessageTemplates(int $tenantId): int
    {
        $now = date('Y-m-d H:i:s');

        $templateRows = Db::table('message_templates')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        if (empty($templateRows)) {
            return 0;
        }

        $existing = array_fill_keys(
            Db::table('message_templates')
                ->where('tenant_id', $tenantId)
                ->column('code'),
            true
        );

        $inserted = 0;
        foreach ($templateRows as $row) {
            if (isset($existing[$row['code']])) {
                continue;
            }
            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            Db::table('message_templates')->insert($row);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * 复制部门（自引用树，需要重映射 parent_id）
     */
    private function copyDepartments(int $tenantId, string $now): void
    {
        $rows = Db::table('departments')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $map = [];

        foreach ($rows as $row) {
            $oldId = (int) $row['id'];
            unset($row['id']);

            $row['tenant_id']  = $tenantId;
            $row['parent_id']  = isset($map[(int) $row['parent_id']]) ? $map[(int) $row['parent_id']] : 0;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $newId       = Db::table('departments')->insertGetId($row);
            $map[$oldId] = $newId;
        }
    }

    /**
     * 复制默认首页装修（从 tenant_id=0 模板复制到新租户）。
     * 让新租户开箱即有默认首页装修，而非空白画布。
     */
    private function copyDiyPages(int $tenantId, string $now): void
    {
        $rows = Db::table('diy_pages')
            ->where('tenant_id', 0)
            ->whereNull('deleted_at')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            unset($row['id']);
            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            Db::table('diy_pages')->insert($row);
        }
    }

    /**
     * 复制默认 PC 前台配置（从 tenant_id=0 模板复制到新租户）。
     */
    private function copyPcConfig(int $tenantId, string $now): void
    {
        $row = Db::table('tenant_pc_configs')->where('tenant_id', 0)->find();
        if (!$row) {
            return;
        }
        unset($row['id']);
        $row['tenant_id'] = $tenantId;
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        Db::table('tenant_pc_configs')->insert($row);
    }

    /**
     * 复制系统配置（从 tenant_id=0 模板复制到新租户）
     */
    private function seedSystemConfigs(int $tenantId, string $now): void
    {
        $table = 'system_configs';

        $templateRows = Db::table($table)
            ->where('tenant_id', 0)
            ->where('config_key', 'not like', 'platform_%')
            ->select()
            ->toArray();

        foreach ($templateRows as $row) {
            unset($row['id']);
            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            Db::table($table)->insert($row);
        }
    }

    /**
     * 创建租户管理员账号
     */
    private function createAdmin(int $tenantId, string $username, string $password, string $now): int
    {
        return Db::table('admins')->insertGetId([
            'tenant_id'  => $tenantId,
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'nickname'   => $username,
            'status'     => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 将管理员绑定到 super_admin 角色
     *
     * @param array<int,int> $roleMap
     */
    private function createAdminRoles(int $tenantId, int $adminId, array $roleMap, string $now): void
    {
        $templateRole = Db::table('roles')
            ->where('tenant_id', 0)
            ->where('name', 'super_admin')
            ->whereNull('deleted_at')
            ->find();

        if (!$templateRole) {
            return;
        }

        $templateRoleId = (int) $templateRole['id'];

        if (!isset($roleMap[$templateRoleId])) {
            return;
        }

        Db::table('admin_roles')->insert([
            'tenant_id'  => $tenantId,
            'admin_id'   => $adminId,
            'role_id'    => $roleMap[$templateRoleId],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
