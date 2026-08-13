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
 * saas:menu-sync
 *
 * 以 tenant_id=0 模板为准，为存量租户补齐缺失的核心菜单与权限（幂等，默认只增不删）。
 * 解决：菜单按租户复制存储，升级脚本只改模板(tenant_id=0)，存量租户拿不到新增的核心菜单。
 *
 * 身份判重：
 *  - type 1/2（目录/页面）：按 menus.name
 *  - type 3（按钮，name 为 NULL）：按 父菜单name + permission
 *  - permissions：按 name（权限码）
 *
 * 排除插件菜单（plugin_menus 记录的行），插件菜单由 saas:plugin-menu-reconcile 按授权管理。
 * 新建菜单授予该租户所有 is_system=1 角色（role_menus）；运行时超管走 is_system 取全部菜单，
 * 非超管角色才依赖 role_menus。
 *
 * 用法：
 *   php think saas:menu-sync                 # 全部存量租户
 *   php think saas:menu-sync --tenant=12     # 仅某租户
 *   php think saas:menu-sync --dry-run       # 只报告将要变更（事务内执行后回滚）
 *   php think saas:menu-sync --update-meta   # 同名菜单同步元数据(标题/排序/父级/图标等)
 */
class SaasMenuSync extends Command
{
    /** 复制菜单时携带的列（不含 id / tenant_id / parent_id / 时间戳，这些单独处理） */
    private const MENU_FIELDS = [
        'type', 'title', 'name', 'path', 'component', 'redirect', 'icon', 'permission',
        'is_hidden', 'is_cache', 'is_affix', 'is_iframe', 'external_link', 'breadcrumb',
        'active_menu', 'meta', 'status', 'sort',
    ];

    /** --update-meta 时允许更新的列（不动 name / type / tenant_id） */
    private const META_FIELDS = [
        'title', 'path', 'component', 'redirect', 'icon', 'permission',
        'is_hidden', 'is_cache', 'is_affix', 'is_iframe', 'external_link', 'breadcrumb',
        'active_menu', 'meta', 'status', 'sort',
    ];

    /** 复制权限时携带的列。 */
    private const PERM_FIELDS = ['name', 'title', 'group', 'description', 'guard_name', 'status', 'sort'];

    /** 全局指纹标记（system_configs，tenant_id=0）：记录上次全量同步到的模板指纹。 */
    private const MARKER_GROUP = 'saas';
    private const MARKER_KEY   = 'menu_template_synced_hash';

    protected function configure(): void
    {
        $this->setName('saas:menu-sync')
            ->setDescription('以模板(tenant_id=0)为准为存量租户补齐核心菜单与权限（幂等，默认只增不删）')
            ->addOption('tenant', null, Option::VALUE_OPTIONAL, '只处理某个 tenant_id', null)
            ->addOption('dry-run', null, Option::VALUE_NONE, '只报告将要变更，不写库（事务回滚）')
            ->addOption('update-meta', null, Option::VALUE_NONE, '同名菜单也同步元数据(标题/排序/父级/图标等)')
            ->addOption('force', null, Option::VALUE_NONE, '忽略指纹门控，强制全量重跑');
    }

    protected function execute(Input $input, Output $output): int
    {
        $only       = $input->getOption('tenant');
        $dry        = (bool) $input->getOption('dry-run');
        $updateMeta = (bool) $input->getOption('update-meta');
        $force      = (bool) $input->getOption('force');

        $fingerprint = $this->currentTemplateFingerprint();

        // 指纹门控：仅对"全量、非 dry-run、非 force"生效（cron 稳态空跑的关键）。
        // --tenant 定向修复 / --force / --dry-run 预演 均绕过门控。
        $fullRun = ($only === null);
        if ($fullRun && !$dry && !$force && $this->syncedHash() === $fingerprint) {
            $output->writeln('menu-sync: 模板未变更，跳过 (fingerprint=' . substr($fingerprint, 0, 8) . ')');
            return 0;
        }

        $q = Db::table('tenants')->whereNull('deleted_at')->where('id', '>', 0);
        if ($only !== null) {
            $q->where('id', (int) $only);
        }
        $ids = $q->column('id');

        $changed = 0;
        $menus   = 0;
        $perms   = 0;
        $failed  = 0;
        foreach ($ids as $tid) {
            try {
                $res = $this->runTenant((int) $tid, $dry, $updateMeta);
            } catch (\Throwable $e) {
                $failed++;
                $output->writeln(sprintf('  tenant %d 失败: %s', $tid, $e->getMessage()));
                continue;
            }
            if ($res['menus'] > 0 || $res['perms'] > 0 || $res['meta'] > 0) {
                $changed++;
                $output->writeln(sprintf(
                    '  tenant %d: +%d 菜单, +%d 权限%s',
                    $tid,
                    $res['menus'],
                    $res['perms'],
                    $res['meta'] > 0 ? ", {$res['meta']} 元数据更新" : ''
                ));
            }
            $menus += $res['menus'];
            $perms += $res['perms'];
        }

        // 仅在"全量、非 dry-run、全部成功"时写回全局标记；有失败则保留旧标记，下次自动重试。
        if ($fullRun && !$dry && $failed === 0) {
            $this->markSynced($fingerprint);
        }

        $output->writeln(sprintf(
            '%smenu-sync done: processed=%d, changed=%d, menus+%d, perms+%d, failed=%d',
            $dry ? '[dry-run] ' : '',
            count($ids),
            $changed,
            $menus,
            $perms,
            $failed
        ));

        return $failed > 0 ? 1 : 0;
    }

    /**
     * 当前模板指纹：核心菜单(name|type|permission|父name|path|component) + 模板权限名 的稳定 sha1。
     * 改了 init.sql 模板，指纹随之变化（无需人工维护版本号）。
     */
    public function currentTemplateFingerprint(): string
    {
        [$tplMenus, $idToName, $tplPerms] = $this->loadTemplate();

        $parts = [];
        foreach ($tplMenus as $m) {
            $parentName = ((int) $m['parent_id'] === 0) ? '' : ($idToName[(int) $m['parent_id']] ?? '?');
            $parts[] = implode('|', [
                (string) ($m['name'] ?? ''),
                (string) (int) $m['type'],
                (string) ($m['permission'] ?? ''),
                $parentName,
                (string) ($m['path'] ?? ''),
                (string) ($m['component'] ?? ''),
            ]);
        }
        sort($parts);

        $permNames = array_map(static fn ($p): string => (string) $p['name'], $tplPerms);
        sort($permNames);

        return sha1(implode("\n", $parts) . "\n##PERMS##\n" . implode(',', $permNames));
    }

    /** 是否需要同步（全局标记 != 当前指纹）。供测试与门控复用。 */
    public function needsSync(): bool
    {
        return $this->syncedHash() !== $this->currentTemplateFingerprint();
    }

    /** 读全局标记指纹（不存在返回 null）。 */
    private function syncedHash(): ?string
    {
        $v = Db::table('system_configs')
            ->where('tenant_id', 0)
            ->where('config_group', self::MARKER_GROUP)
            ->where('config_key', self::MARKER_KEY)
            ->value('config_value');
        return $v === null ? null : (string) $v;
    }

    /** 写回全局标记指纹（upsert）。 */
    private function markSynced(string $hash): void
    {
        $now = date('Y-m-d H:i:s');
        $exists = Db::table('system_configs')
            ->where('tenant_id', 0)
            ->where('config_group', self::MARKER_GROUP)
            ->where('config_key', self::MARKER_KEY)
            ->find();
        if ($exists) {
            Db::table('system_configs')->where('id', (int) $exists['id'])
                ->update(['config_value' => $hash, 'updated_at' => $now]);
        } else {
            Db::table('system_configs')->insert([
                'tenant_id'    => 0,
                'config_group' => self::MARKER_GROUP,
                'config_key'   => self::MARKER_KEY,
                'config_value' => $hash,
                'config_type'  => 'string',
                'config_name'  => '装修核心菜单同步指纹',
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    /**
     * 对单个租户执行同步（公开以便测试直接调用）。
     * 整体在事务内执行；dry-run 末尾回滚，从而既得到准确计数又不落库。
     *
     * @return array{menus:int,perms:int,meta:int}
     */
    public function runTenant(int $tid, bool $dry = false, bool $updateMeta = false): array
    {
        [$tplMenus, $tplIdToName, $tplPerms] = $this->loadTemplate();
        $now = date('Y-m-d H:i:s');
        $menusAdded = 0;
        $permsAdded = 0;
        $metaUpdated = 0;
        $roleIds = $this->superRoleIds($tid);

        Db::startTrans();
        try {
            // 该租户现有菜单 name->id（仅 type 1/2 有 name）
            $existing = Db::table('menus')->where('tenant_id', $tid)->whereNull('deleted_at')->select();
            $nameToId = [];
            foreach ($existing as $row) {
                if ((int) $row['type'] !== 3 && $row['name'] !== null && $row['name'] !== '') {
                    $nameToId[$row['name']] = (int) $row['id'];
                }
            }

            // 第一遍：目录/页面（type 1/2），按 name 判重；模板已按 parent_id 升序 → 父先子后
            foreach ($tplMenus as $m) {
                if ((int) $m['type'] === 3) {
                    continue;
                }
                $name = $m['name'];
                if ($name === null || $name === '') {
                    continue; // 非按钮却无 name，异常数据，跳过
                }
                $parentId = $this->resolveParentId($m, $tplIdToName, $nameToId);
                if ($parentId === null) {
                    continue; // 父尚未建（理论上不发生）；幂等重跑可补
                }

                if (isset($nameToId[$name])) {
                    if ($updateMeta) {
                        $patch = ['parent_id' => $parentId, 'updated_at' => $now];
                        foreach (self::META_FIELDS as $f) {
                            $patch[$f] = $m[$f];
                        }
                        Db::table('menus')->where('id', $nameToId[$name])->update($patch);
                        $metaUpdated++;
                    }
                    continue;
                }

                $newId = (int) Db::table('menus')->insertGetId($this->menuRow($m, $tid, $parentId, $now));
                $this->grantMenu($tid, $newId, $roleIds, $now);
                $nameToId[$name] = $newId;
                $menusAdded++;
            }

            // 第二遍：按钮（type 3），按 父name + permission 判重
            $btnSet = [];
            $btnRows = Db::table('menus')->where('tenant_id', $tid)->where('type', 3)->whereNull('deleted_at')->select();
            foreach ($btnRows as $b) {
                $btnSet[((int) $b['parent_id']) . '|' . (string) $b['permission']] = true;
            }
            foreach ($tplMenus as $m) {
                if ((int) $m['type'] !== 3) {
                    continue;
                }
                $parentName = $tplIdToName[(int) $m['parent_id']] ?? null;
                if ($parentName === null) {
                    continue;
                }
                $parentId = $nameToId[$parentName] ?? null;
                if ($parentId === null) {
                    continue; // 父菜单不在该租户（核心父都应存在）；幂等重跑可补
                }
                $key = $parentId . '|' . (string) $m['permission'];
                if (isset($btnSet[$key])) {
                    continue;
                }
                $newId = (int) Db::table('menus')->insertGetId($this->menuRow($m, $tid, $parentId, $now));
                $this->grantMenu($tid, $newId, $roleIds, $now);
                $btnSet[$key] = true;
                $menusAdded++;
            }

            // 权限同步（按 name 补缺；供"角色授权"界面勾选。超管运行时走 '*' 不依赖此表）
            $havePerm = array_flip(
                Db::table('permissions')->where('tenant_id', $tid)->whereNull('deleted_at')->column('name')
            );
            foreach ($tplPerms as $p) {
                if (isset($havePerm[$p['name']])) {
                    continue;
                }
                $row = ['tenant_id' => $tid, 'created_at' => $now, 'updated_at' => $now];
                foreach (self::PERM_FIELDS as $f) {
                    $row[$f] = $p[$f];
                }
                Db::table('permissions')->insert($row);
                $permsAdded++;
            }

            if ($dry) {
                Db::rollback();
            } else {
                Db::commit();
            }
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return ['menus' => $menusAdded, 'perms' => $permsAdded, 'meta' => $metaUpdated];
    }

    /**
     * 载入模板：核心菜单(排除插件菜单，按 parent_id 升序父先子后) + 模板id→name 映射 + 模板权限。
     *
     * @return array{0:array,1:array<int,string>,2:array}
     */
    private function loadTemplate(): array
    {
        $pluginMenuIds = Db::table('plugin_menus')->column('menu_id');
        $mq = Db::table('menus')->where('tenant_id', 0)->whereNull('deleted_at');
        if (!empty($pluginMenuIds)) {
            $mq->whereNotIn('id', $pluginMenuIds);
        }
        $tplMenus = $mq->order('parent_id', 'asc')->order('sort', 'asc')->select()->all();

        $idToName = [];
        foreach ($tplMenus as $m) {
            if ($m['name'] !== null && $m['name'] !== '') {
                $idToName[(int) $m['id']] = (string) $m['name'];
            }
        }

        $tplPerms = Db::table('permissions')->where('tenant_id', 0)->whereNull('deleted_at')->select()->all();

        return [$tplMenus, $idToName, $tplPerms];
    }

    /** 用模板行 + 解析后的 parent_id 组装一条该租户的菜单插入数据。 */
    private function menuRow(array $tpl, int $tid, int $parentId, string $now): array
    {
        $row = ['tenant_id' => $tid, 'parent_id' => $parentId, 'created_at' => $now, 'updated_at' => $now];
        foreach (self::MENU_FIELDS as $f) {
            $row[$f] = $tpl[$f];
        }
        return $row;
    }

    /** 把模板菜单的 parent_id 解析成该租户的 parent_id：root→0；否则按"模板父name → 租户同名菜单id"。 */
    private function resolveParentId(array $tpl, array $idToName, array $nameToId): ?int
    {
        if ((int) $tpl['parent_id'] === 0) {
            return 0;
        }
        $parentName = $idToName[(int) $tpl['parent_id']] ?? null;
        if ($parentName === null) {
            return null;
        }
        return $nameToId[$parentName] ?? null;
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

    /** 该租户的所有系统角色（is_system=1）。 */
    private function superRoleIds(int $tid): array
    {
        return array_map('intval', Db::table('roles')
            ->where('tenant_id', $tid)
            ->where('is_system', 1)
            ->whereNull('deleted_at')
            ->column('id'));
    }
}
