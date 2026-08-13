<?php

/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * saas:diy-menu-reconcile
 *
 * 为存量租户对齐装修菜单树（幂等）。
 * - 插入装修一级目录 + 页面装修/发布管理分组及子菜单（含 DiyLaunch，若缺失）
 * - 将菜单授权给该租户所有 is_system=1 角色（via role_menus）
 * - 删除该租户的 移动端配置（MobileConfig）菜单及其子项
 */
class SaasDiyMenuReconcile extends Command
{
    /** 二级分组目录：name => [title, icon, sort]（空 path 目录，作为 sub-sidebar 分组标题） */
    private const GROUPS = [
        'DiyPageGroup'    => ['页面装修', 'i-svg:layout-grid', 1],
        'DiyPublishGroup' => ['发布管理', 'i-svg:rocket',      2],
    ];

    /** 子菜单定义：name => [title, path, component, icon, permission, sort, groupName] */
    private const CHILDREN = [
        'DiyHome'   => ['页面装修', '/diy/home',   'diy/decorate-list',   'i-svg:house',       'diy.home.view',      1, 'DiyPageGroup'],
        'DiyLaunch' => ['启动与首页', '/diy/launch', 'diy/launch',         'i-svg:rocket',      'mobile.config.view', 2, 'DiyPageGroup'],
        'DiyPages'  => ['自定义页面', '/diy/pages', 'diy/pages', 'i-svg:layout-list', 'diy.page.view',      3, 'DiyPageGroup'],
        'DiyTabbar' => ['底部导航', '/diy/tabbar', 'diy/tabbar', 'i-svg:layout-list', 'mobile.config.view', 4, 'DiyPageGroup'],
        'DiyTheme'  => ['主题风格', '/diy/theme',  'diy/theme',  'i-svg:palette',     'mobile.config.view', 5, 'DiyPageGroup'],
        'DiyBasic'  => ['基础设置', '/diy/basic',  'diy/basic',  'i-svg:cog',         'mobile.config.view', 1, 'DiyPublishGroup'],
        'DiyBuild'  => ['打包发布', '/diy/build',  'diy/build',  'i-svg:monitor',     'mobile.config.view', 2, 'DiyPublishGroup'],
    ];

    /** 写权限按钮：parentChildName => list of [title, permission, sort] */
    private const BUTTONS = [
        'DiyHome'  => [
            ['保存', 'diy.home.save', 1],
            ['发布', 'diy.home.publish', 2],
            ['版本列表', 'diy.home.version.view', 3],
            ['回滚版本', 'diy.home.version.restore', 4],
        ],
        'DiyLaunch' => [['保存', 'mobile.config.update', 1]],
        'DiyBasic'  => [['保存', 'mobile.config.update', 1]],
        'DiyPages'  => [
            ['创建', 'diy.page.create', 1],
            ['编辑', 'diy.page.update', 2],
            ['删除', 'diy.page.delete', 3],
            ['保存', 'diy.page.save', 4],
            ['发布', 'diy.page.publish', 5],
        ],
    ];

    protected function configure(): void
    {
        $this->setName('saas:diy-menu-reconcile')
            ->setDescription('为存量租户对齐装修菜单树（幂等）')
            ->addOption('tenant', null, Option::VALUE_OPTIONAL, '只处理某个 tenant_id', null);
    }

    protected function execute(Input $input, Output $output): int
    {
        $only = $input->getOption('tenant');
        $q = Db::table('tenants')->whereNull('deleted_at')->where('id', '>', 0);
        if ($only !== null) {
            $q->where('id', (int) $only);
        }
        $ids = $q->column('id');

        $changed = 0;
        foreach ($ids as $tid) {
            if ($this->reconcileTenant((int) $tid)) {
                $changed++;
            }
        }

        $output->writeln('diy-menu-reconcile done: processed=' . count($ids) . ", changed={$changed}");

        return 0;
    }

    private function reconcileTenant(int $tid): bool
    {
        $now     = date('Y-m-d H:i:s');
        $touched = false;

        $roleIds = $this->superRoleIds($tid);

        // 1. 删除该租户移动端配置菜单（含子项）及其 role_menus
        $mobileIds = Db::table('menus')
            ->where('tenant_id', $tid)
            ->where('name', 'MobileConfig')
            ->column('id');

        if ($mobileIds) {
            $childIds = Db::table('menus')
                ->where('tenant_id', $tid)
                ->whereIn('parent_id', $mobileIds)
                ->column('id');
            $allIds   = array_merge($mobileIds, $childIds);

            Db::table('menus')
                ->where('tenant_id', $tid)
                ->whereIn('id', $allIds)
                ->delete();

            Db::table('role_menus')
                ->where('tenant_id', $tid)
                ->whereIn('menu_id', $allIds)
                ->delete();

            $touched = true;
        }

        // 2. 装修一级目录
        $diy = Db::table('menus')
            ->where('tenant_id', $tid)
            ->where('name', 'Diy')
            ->find();

        if ($diy === null) {
            $diyId = (int) Db::table('menus')->insertGetId([
                'tenant_id'  => $tid,
                'parent_id'  => 0,
                'type'       => 1,
                'title'      => '装修',
                'name'       => 'Diy',
                'path'       => '/diy',
                'component'  => 'LAYOUT',
                'redirect'   => '/diy/home',
                'icon'       => 'i-svg:paint-roller',
                'permission' => 'diy.home.view',
                'is_hidden'  => 0,
                'is_cache'   => 1,
                'status'     => 1,
                'sort'       => 800,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->grantMenu($tid, $diyId, $roleIds, $now);
            $touched = true;
        } else {
            $diyId = (int) $diy['id'];
        }

        // 3. 二级分组目录（页面装修 / 发布管理）——缺则建+授予；存在则确保挂在 Diy 下
        $groupIds = [];
        foreach (self::GROUPS as $gname => [$gtitle, $gicon, $gsort]) {
            $g = Db::table('menus')->where('tenant_id', $tid)->where('name', $gname)->find();
            if ($g === null) {
                $gid = (int) Db::table('menus')->insertGetId([
                    'tenant_id'  => $tid,
                    'parent_id'  => $diyId,
                    'type'       => 1,
                    'title'      => $gtitle,
                    'name'       => $gname,
                    'path'       => '',
                    'component'  => null,
                    'icon'       => $gicon,
                    'permission' => null,
                    'is_hidden'  => 0,
                    'is_cache'   => 1,
                    'status'     => 1,
                    'sort'       => $gsort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->grantMenu($tid, $gid, $roleIds, $now);
                $touched = true;
            } else {
                $gid = (int) $g['id'];
                if ((int) $g['parent_id'] !== $diyId) {
                    Db::table('menus')->where('id', $gid)->update(['parent_id' => $diyId, 'updated_at' => $now]);
                    $touched = true;
                }
            }
            $groupIds[$gname] = $gid;
        }

        // 4. 子菜单：缺则建于对应分组；已存在则迁移 parent/sort/title（扁平→分组、主题→主题风格、重排）
        foreach (self::CHILDREN as $name => [$title, $path, $component, $icon, $perm, $sort, $groupName]) {
            $groupId  = $groupIds[$groupName];
            $existing = Db::table('menus')->where('tenant_id', $tid)->where('name', $name)->find();
            if ($existing === null) {
                $childId = (int) Db::table('menus')->insertGetId([
                    'tenant_id'  => $tid,
                    'parent_id'  => $groupId,
                    'type'       => 2,
                    'title'      => $title,
                    'name'       => $name,
                    'path'       => $path,
                    'component'  => $component,
                    'icon'       => $icon,
                    'permission' => $perm,
                    'is_hidden'  => 0,
                    'is_cache'   => 1,
                    'status'     => 1,
                    'sort'       => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->grantMenu($tid, $childId, $roleIds, $now);
                $touched = true;
            } elseif ((int) $existing['parent_id'] !== $groupId
                || (int) $existing['sort'] !== $sort
                || (string) $existing['title'] !== $title
                || (string) $existing['component'] !== $component
                || (string) $existing['path'] !== $path
                || (string) $existing['permission'] !== $perm) {
                Db::table('menus')->where('id', (int) $existing['id'])->update([
                    'parent_id'  => $groupId,
                    'sort'       => $sort,
                    'title'      => $title,
                    'component'  => $component,
                    'path'       => $path,
                    'permission' => $perm,
                    'updated_at' => $now,
                ]);
                $touched = true;
            }
        }

        // 4. 写权限按钮（type 3，幂等：按 tenant_id+parent_id+type+permission 判重）
        foreach (self::BUTTONS as $parentName => $buttons) {
            $parentId = (int) Db::table('menus')
                ->where('tenant_id', $tid)
                ->where('name', $parentName)
                ->value('id');

            if (!$parentId) {
                continue;
            }

            foreach ($buttons as [$btnTitle, $btnPerm, $btnSort]) {
                $exists = Db::table('menus')
                    ->where('tenant_id', $tid)
                    ->where('parent_id', $parentId)
                    ->where('type', 3)
                    ->where('permission', $btnPerm)
                    ->count() > 0;

                if ($exists) {
                    continue;
                }

                $btnId = (int) Db::table('menus')->insertGetId([
                    'tenant_id'  => $tid,
                    'parent_id'  => $parentId,
                    'type'       => 3,
                    'title'      => $btnTitle,
                    'permission' => $btnPerm,
                    'is_hidden'  => 0,
                    'is_cache'   => 1,
                    'status'     => 1,
                    'sort'       => $btnSort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->grantMenu($tid, $btnId, $roleIds, $now);
                $touched = true;
            }
        }

        return $touched;
    }

    private function grantMenu(int $tid, int $menuId, array $roleIds, string $now): void
    {
        foreach ($roleIds as $roleId) {
            $has = Db::table('role_menus')
                ->where('tenant_id', $tid)
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->count() > 0;

            if (!$has) {
                Db::table('role_menus')->insert([
                    'tenant_id'  => $tid,
                    'role_id'    => $roleId,
                    'menu_id'    => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** 该租户的所有系统角色（is_system=1）。运行时超管判定用 is_system，故按此授予菜单。 */
    private function superRoleIds(int $tid): array
    {
        return array_map('intval', Db::table('roles')
            ->where('tenant_id', $tid)
            ->where('is_system', 1)
            ->whereNull('deleted_at')
            ->column('id'));
    }
}
