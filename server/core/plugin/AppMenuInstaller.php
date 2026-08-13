<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use think\facade\Db;

/**
 * 安装/卸载/复制 App 贡献的菜单。
 * 责任：仅操作 menus 表 + plugin_menus 关联，不涉及插件生命周期判定。
 */
class AppMenuInstaller
{
    /**
     * 写入模板菜单（tenant_id=0）并记录 plugin_menus 关联。
     *
     * 软卸载（PluginService::uninstall）按设计保留菜单与角色勾选，仅 purge 才物理删。
     * 因此重新上传安装同 code 插件会产生新的 plugins 行（新 pluginId），但 tenant_id=0
     * 的模板菜单仍是旧行——遇到 code 冲突时优先判断是否为「同插件残留」，是则收养
     * （改绑 plugin_menus.plugin_id + 按新 manifest 刷新菜单 meta），否则仍视为真实冲突。
     *
     * 返回本次执行清单，供 saga 补偿精确回滚（只删本次新建，不动收养来的残留菜单）：
     * - created: 本次新插入的模板菜单 id（tenant_id=0）
     * - adopted: 本次收养复用的既有菜单 id => 改绑前的旧 plugin_id
     *
     * @param int $pluginId
     * @param string $pluginCode 当前安装插件的 code（manifest['code']），用于比对残留菜单归属
     * @param array<int, array{code:string,name:string,path:string,parent_code?:string,icon?:string,sort?:int,component?:string}> $menus
     * @return array{created: int[], adopted: array<int, int>}
     */
    public function installMenuTemplates(int $pluginId, string $pluginCode, array $menus): array
    {
        $now = date('Y-m-d H:i:s');
        $codeToId = [];
        $newMenuIds = [];
        $adopted = [];

        $ordered = $this->topoSort($menus);

        // 预扫一遍：被任何一项当作 parent_code 引用的 code，视为目录（type=1）
        $hasChildren = [];
        foreach ($menus as $m) {
            if (!empty($m['parent_code'])) {
                $hasChildren[$m['parent_code']] = true;
            }
        }

        // 顶级 app 菜单的 sort 自动分配区段：
        // [100, 499] 介于 Workbench(0) 之后、User(500) 之前；
        // manifest 没声明 sort（或写 0）时使用 "现有 app 顶级菜单最大 sort + 10"，
        // 落在该区段内；超过 490 时收敛到 490（不让 app 越过 User）。
        $autoSortBase = $this->nextAppTopSort();

        foreach ($ordered as $menu) {
            $parentId = isset($menu['parent_code']) && isset($codeToId[$menu['parent_code']])
                ? $codeToId[$menu['parent_code']]
                : 0;

            // type: 1=目录 / 2=菜单 / 3=按钮
            // 漏写会导致 menus 的前端路由查询 `where type <> 3` 把 NULL 行整个过滤掉。
            // 推导规则：manifest 显式声明则尊重；否则有子节点 = 目录，无子节点 = 菜单。
            $type = isset($menu['type'])
                ? (int) $menu['type']
                : (isset($hasChildren[$menu['code']]) ? 1 : 2);

            // sort：顶级菜单（parent_code 为空）走 [100, 499] 自动区段；子菜单尊重 manifest
            $manifestSort = (int) ($menu['sort'] ?? 0);
            $isTopLevel = empty($menu['parent_code']);
            $sort = $isTopLevel
                ? ($manifestSort > 0 && $manifestSort < 500 ? $manifestSort : $autoSortBase)
                : $manifestSort;

            $existing = Db::table('menus')
                ->where('tenant_id', 0)
                ->where('code', $menu['code'])
                ->value('id');
            if ($existing) {
                $adoption = $this->tryAdoptResidualMenu(
                    (int) $existing,
                    $pluginId,
                    $pluginCode,
                    $menu,
                    $type,
                    $sort,
                    $now
                );
                if ($adoption !== null) {
                    // 收养：既有行复用，不进 $newMenuIds（role_menus 关联本就保留，
                    // 不重挂避免 linkMenusToSuperRole 产生重复行）；但要让子菜单能挂靠。
                    $codeToId[$menu['code']] = $adoption['menuId'];
                    $adopted[$adoption['menuId']] = $adoption['oldPluginId'];
                    continue;
                }
                throw new \RuntimeException(
                    "menu code 冲突：{$menu['code']} 已存在（如为其他插件残留，请先清理数据）"
                );
            }

            $menuId = Db::table('menus')->insertGetId([
                'tenant_id'  => 0,
                'parent_id'  => $parentId,
                'code'       => $menu['code'],
                'name'       => $menu['name'],
                // 前端读 meta.title 渲染左侧栏；漏写会让菜单出现但标题为空。
                // manifest 显式声明 title 则尊重，否则用 name 兜底。
                'title'      => $menu['title'] ?? $menu['name'],
                'path'       => $menu['path'],
                'component'  => $menu['component'] ?? '',
                'is_hidden'  => (int) ($menu['is_hidden'] ?? 0),
                'icon'       => $menu['icon'] ?? '',
                'sort'       => $sort,
                // permission：页面菜单承载列表/查看权限，供非超管角色被授权后通过校验
                'permission' => (string) ($menu['permission'] ?? ''),
                'type'       => $type,
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Db::table('plugin_menus')->insert([
                'plugin_id'  => $pluginId,
                'menu_id'    => $menuId,
                'sort'       => (int) ($menu['sort'] ?? 0),
                'created_at' => $now,
            ]);

            $codeToId[$menu['code']] = $menuId;
            $newMenuIds[] = (int) $menuId;

            // 展开操作按钮（type=3）：增删改等细粒度权限，供租户按功能授权
            PluginMenuButtons::sync(0, (int) $menuId, (array) ($menu['buttons'] ?? []), $pluginId, $newMenuIds);
        }

        // 把新模板菜单挂到 tenant_id=0 的超管角色，后续 TenantInitService 拷贝
        // 新租户时会把这层关系一起带过去，保证新建租户开箱即见。
        $this->linkMenusToSuperRole(0, $newMenuIds);

        return ['created' => $newMenuIds, 'adopted' => $adopted];
    }

    /**
     * saga 补偿：仅回滚本次 installMenuTemplates 的执行结果，不物理删除收养来的残留菜单。
     *
     * - adopted：把 plugin_menus.plugin_id 改绑回收养前的旧 plugin_id（menu 行本身不动，
     *   它是收养前就存在的残留数据，即便本次安装失败也不应消失）。同 id revive 场景下
     *   旧、新 plugin_id 相同，这里是无害的同值更新。
     * - created：按本次新插入的模板 id 反查 code，级联删除模板行 + 所有租户同 code 副本 +
     *   对应 role_menus + plugin_menus——范围仅限这些 code，不触达收养菜单的 code。
     *
     * @param array{created: int[], adopted: array<int, int>} $result installMenuTemplates 的返回值
     */
    public function rollbackInstall(int $pluginId, array $result): void
    {
        foreach ($result['adopted'] as $menuId => $oldPluginId) {
            Db::table('plugin_menus')
                ->where('menu_id', (int) $menuId)
                ->update(['plugin_id' => (int) $oldPluginId]);
        }

        $createdIds = $result['created'];
        if (empty($createdIds)) {
            return;
        }

        $codes = Db::table('menus')
            ->where('tenant_id', 0)
            ->whereIn('id', $createdIds)
            ->column('code');

        if (!empty($codes)) {
            // 先清 role_menus，否则会留孤儿 menu_id（pivot 表无外键级联）。
            // 范围：所有租户中这批新建 code 命中的菜单 id。
            $allMenuIds = Db::table('menus')->whereIn('code', $codes)->column('id');
            if (!empty($allMenuIds)) {
                Db::table('role_menus')->whereIn('menu_id', $allMenuIds)->delete();
            }
            Db::table('menus')->whereIn('code', $codes)->delete();
        }

        // plugin_menus 只在模板行（tenant_id=0）上有记录，租户副本没有；
        // 用 created 的模板 id 精确删，避免用 plugin_id 误删刚被上面改绑回旧 plugin_id 的收养行
        // （同 id revive 场景下 oldPluginId === pluginId，用 plugin_id 过滤会连收养行一起删掉）。
        Db::table('plugin_menus')->whereIn('menu_id', $createdIds)->delete();
    }

    /**
     * 把这个插件的模板菜单复制到指定租户。幂等。
     */
    public function copyToTenant(int $pluginId, int $tenantId): void
    {
        $now = date('Y-m-d H:i:s');
        $menuIds = Db::table('plugin_menus')->where('plugin_id', $pluginId)->column('menu_id');
        if (empty($menuIds)) {
            return;
        }

        $templates = Db::table('menus')
            ->where('tenant_id', 0)
            ->whereIn('id', $menuIds)
            ->order('parent_id', 'asc')
            ->select()
            ->toArray();

        $oldToNew = [];
        foreach ($templates as $row) {
            $templateId = (int) $row['id'];

            $existing = Db::table('menus')
                ->where('tenant_id', $tenantId)
                ->where('code', $row['code'])
                ->value('id');
            if ($existing) {
                $oldToNew[$templateId] = (int) $existing;
                continue;
            }

            $newParent = isset($oldToNew[(int) $row['parent_id']]) ? $oldToNew[(int) $row['parent_id']] : 0;
            unset($row['id']);
            $row['tenant_id']  = $tenantId;
            $row['parent_id']  = $newParent;
            $row['is_hidden']  = (int) ($row['is_hidden'] ?? 0);
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $oldToNew[$templateId] = (int) Db::table('menus')->insertGetId($row);
        }

        // 把这批菜单挂到该租户的超管角色，否则即便菜单已写入 menus 表，
        // 角色侧没有引用，登录后 getFrontendRoutes 也取不到（admins 表无 is_super 列）。
        $this->linkMenusToSuperRole($tenantId, array_values($oldToNew));
    }

    /**
     * 物理删除该插件的菜单（模板 + 所有租户的副本）。
     */
    public function removeForPlugin(int $pluginId): void
    {
        $menuIds = Db::table('plugin_menus')->where('plugin_id', $pluginId)->column('menu_id');
        if (empty($menuIds)) {
            return;
        }

        $codes = Db::table('menus')
            ->where('tenant_id', 0)
            ->whereIn('id', $menuIds)
            ->column('code');

        if (!empty($codes)) {
            // 先清 role_menus，否则会留孤儿 menu_id（pivot 表无外键级联）。
            // 范围：所有租户中 code 命中的菜单 id。
            $allMenuIds = Db::table('menus')->whereIn('code', $codes)->column('id');
            if (!empty($allMenuIds)) {
                Db::table('role_menus')->whereIn('menu_id', $allMenuIds)->delete();
            }
            Db::table('menus')->whereIn('code', $codes)->delete();
        }

        Db::table('plugin_menus')->where('plugin_id', $pluginId)->delete();
    }

    /**
     * 删除指定租户下该插件的菜单 + 对应 role_menus 行。
     *
     * 用于：plan 取消某个 app 插件授权、租户改套餐失去该插件。
     * 通过 plugin_menus.menu_id → menus(tenant_id=0).code 反查 plugin 的菜单 code，
     * 然后定位该租户下同 code 的菜单（copyToTenant 时 code 保持一致）。
     * 幂等。
     */
    public function removeForTenant(int $pluginId, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $templateMenuIds = Db::table('plugin_menus')
            ->where('plugin_id', $pluginId)
            ->column('menu_id');
        if (empty($templateMenuIds)) {
            return;
        }
        $codes = Db::table('menus')
            ->where('tenant_id', 0)
            ->whereIn('id', $templateMenuIds)
            ->column('code');
        if (empty($codes)) {
            return;
        }
        $tenantMenuIds = Db::table('menus')
            ->where('tenant_id', $tenantId)
            ->whereIn('code', $codes)
            ->column('id');
        if (empty($tenantMenuIds)) {
            return;
        }
        Db::table('role_menus')->whereIn('menu_id', $tenantMenuIds)->delete();
        Db::table('menus')->whereIn('id', $tenantMenuIds)->delete();
    }

    /**
     * 判断 tenant_id=0 下与新插件 code 冲突的既有菜单是否为「同插件残留」，是则收养。
     *
     * 收养场景：插件被软卸载（PluginService::uninstall 保留 menus/plugin_menus/role_menus）
     * 后重新上传安装，产生新的 plugins 行（新 pluginId），但模板菜单还是旧行。
     * 判定依据：plugin_menus.menu_id 反查旧 plugin_id，再直查 plugins 表（含软删行，
     * 绕开 Model 的 SoftDelete 全局作用域）拿到旧行的 code，与本次安装的 $pluginCode 比对。
     *
     * - 无 plugin_menus 关联（孤儿同名菜单）或指向的插件 code 不同 → 判不了归属，返回 null（真实冲突）
     * - code 相同 → 改绑 plugin_menus.plugin_id 到新 pluginId，并按新 manifest 刷新菜单 meta
     *   （parent_id 保持既有不动），返回既有 menu_id + 改绑前的旧 plugin_id（供 saga 补偿回滚用）
     *
     * @param array<string, mixed> $menu
     * @return array{menuId: int, oldPluginId: int}|null
     */
    private function tryAdoptResidualMenu(
        int $existingMenuId,
        int $pluginId,
        string $pluginCode,
        array $menu,
        int $type,
        int $sort,
        string $now
    ): ?array {
        $oldPluginId = Db::table('plugin_menus')->where('menu_id', $existingMenuId)->value('plugin_id');
        if (!$oldPluginId) {
            return null;
        }

        // 直查 plugins 表绕过 SoftDelete，软卸载后的旧行也要能读到 code
        $oldCode = Db::table('plugins')->where('id', (int) $oldPluginId)->value('code');
        if ($oldCode === null || (string) $oldCode !== $pluginCode) {
            return null;
        }

        Db::table('plugin_menus')
            ->where('menu_id', $existingMenuId)
            ->update(['plugin_id' => $pluginId]);

        Db::table('menus')
            ->where('id', $existingMenuId)
            ->update([
                'name'       => $menu['name'],
                'title'      => $menu['title'] ?? $menu['name'],
                'path'       => $menu['path'],
                'component'  => $menu['component'] ?? '',
                'is_hidden'  => (int) ($menu['is_hidden'] ?? 0),
                'icon'       => $menu['icon'] ?? '',
                'type'       => $type,
                'sort'       => $sort,
                'status'     => 1,
                'updated_at' => $now,
            ]);

        return ['menuId' => $existingMenuId, 'oldPluginId' => (int) $oldPluginId];
    }

    /**
     * 为下一个 app 顶级菜单计算 sort：取 menus(tenant_id=0, parent_id=0, sort in [100,499])
     * 的最大 sort + 10；起步 100；上限 490。
     */
    private function nextAppTopSort(): int
    {
        $max = (int) Db::table('menus')
            ->where('tenant_id', 0)
            ->where('parent_id', 0)
            ->whereNull('deleted_at')
            ->where('sort', '>=', 100)
            ->where('sort', '<', 500)
            ->max('sort');
        $next = $max > 0 ? $max + 10 : 100;
        return $next > 490 ? 490 : $next;
    }

    /**
     * 把一批菜单挂到指定 tenant 下所有 `is_system=1` 的超管角色（幂等）。
     * 范围限制为 is_system 是有意的：避免把新菜单意外加给业务自定义角色。
     *
     * @param array<int, int> $menuIds
     */
    private function linkMenusToSuperRole(int $tenantId, array $menuIds): void
    {
        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        if (empty($menuIds)) {
            return;
        }

        $roleIds = Db::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('is_system', 1)
            ->whereNull('deleted_at')
            ->column('id');
        if (empty($roleIds)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($roleIds as $roleId) {
            $existing = Db::table('role_menus')
                ->where('role_id', $roleId)
                ->whereIn('menu_id', $menuIds)
                ->column('menu_id');
            $missing = array_diff($menuIds, array_map('intval', $existing));
            foreach ($missing as $mid) {
                Db::table('role_menus')->insert([
                    'tenant_id'  => $tenantId,
                    'role_id'    => $roleId,
                    'menu_id'    => $mid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * 拓扑排序：确保父菜单在子菜单之前处理。
     *
     * @param array<int, array{code:string,parent_code?:string}> $menus
     * @return array<int, array<string, mixed>>
     */
    private function topoSort(array $menus): array
    {
        $byCode = [];
        foreach ($menus as $m) {
            $byCode[$m['code']] = $m;
        }
        $visited = [];
        $result  = [];

        $visit = function (array $m) use (&$visit, &$visited, &$result, $byCode): void {
            if (isset($visited[$m['code']])) {
                return;
            }
            $visited[$m['code']] = true;
            if (!empty($m['parent_code']) && isset($byCode[$m['parent_code']])) {
                $visit($byCode[$m['parent_code']]);
            }
            $result[] = $m;
        };

        foreach ($menus as $m) {
            $visit($m);
        }

        return $result;
    }
}
