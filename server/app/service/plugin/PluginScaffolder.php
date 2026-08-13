<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\plugin;

use core\exception\BusinessException;
use core\plugin\PluginManifestValidator;
use think\facade\App;

/**
 * 插件脚手架生成器（纯逻辑，无交互/无输出）。
 * 由 plugin:create 命令收集配置后调用；也可被测试直接调用。
 */
class PluginScaffolder
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? App::getRootPath(), '/\\');
    }

    /**
     * @param array<string,mixed> $config code/name/version/kind/author/description/
     *                                     menu_name?/menu_path?/with_uniapp/force
     * @return string[] 已创建文件的绝对路径
     */
    public function generate(array $config): array
    {
        foreach (['code', 'name', 'version', 'kind'] as $required) {
            if (!isset($config[$required]) || (string) $config[$required] === '') {
                throw new BusinessException("generate() 缺少必填参数：{$required}", 422);
            }
        }

        $code = (string) $config['code'];
        $dir  = $this->root . '/plugins/' . $code;
        if (is_dir($dir) && empty($config['force'])) {
            throw new BusinessException("插件目录已存在：{$dir}（用 --force 覆盖）", 409);
        }

        $pascal = $this->pascal($code);
        $created = [];

        $manifest = $this->buildManifest($config, $pascal);

        // 先在内存里自校验，避免把坏文件落到磁盘
        $errors = (new PluginManifestValidator())->validate($manifest);
        if ($errors !== []) {
            throw new BusinessException('脚手架生成的 plugin.json 未通过校验：' . implode('; ', $errors), 500);
        }

        $this->put(
            $dir . '/plugin.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            $created
        );

        $this->writeIcon($dir, $created);
        $this->put($dir . '/README.md', $this->readmeTpl($config), $created);
        $this->put($dir . '/app/hooks/Lifecycle.php', $this->lifecycleTpl($pascal), $created);
        $this->put($dir . "/app/{$pascal}Service.php", $this->serviceTpl($pascal), $created);
        $this->put($dir . '/app/tenantapi/route.php', $this->routeTpl($code), $created);
        $this->put($dir . '/database/install.sql', $this->installSqlTpl($code), $created);
        $this->put($dir . '/database/uninstall.sql', $this->uninstallSqlTpl($code), $created);
        $this->put($dir . '/database/testdata.sql', $this->testdataSqlTpl($code), $created);

        if (($config['kind'] ?? '') === 'app') {
            $camel = lcfirst($pascal);
            $this->put($dir . '/tenant/views/index.vue', $this->vueTpl($config), $created);
            $this->put($dir . "/tenant/api/{$camel}.ts", $this->apiTpl($code), $created);
        }

        if (!empty($config['with_uniapp'])) {
            $this->put($dir . '/uniapp/pages/index/index.vue', $this->uniappTpl($config), $created);
        }

        return $created;
    }

    /** @param array<string,mixed> $config */
    private function buildManifest(array $config, string $pascal): array
    {
        $code = (string) $config['code'];

        $tenant = [
            'permissions' => [
                ['code' => $this->snake($code) . '.list', 'name' => $config['name'] . ' 列表'],
            ],
        ];
        if (($config['kind'] ?? '') === 'app') {
            $menuPath = (string) ($config['menu_path'] ?? '/' . $code);
            $tenant['menus'] = [
                ['code' => $code, 'name' => (string) ($config['menu_name'] ?? $config['name']),
                 'path' => $menuPath, 'icon' => 'i-svg:grid', 'sort' => 100],
                ['code' => $code . '-index', 'parent_code' => $code, 'name' => '列表',
                 'path' => $menuPath . '/index', 'component' => "plugins/{$code}/index"],
            ];
        }

        $manifest = [
            'code'        => $code,
            'name'        => (string) $config['name'],
            'version'     => (string) $config['version'],
            'author'      => (string) ($config['author'] ?? ''),
            'description' => (string) ($config['description'] ?? ''),
            'kind'        => (string) $config['kind'],
            'category'    => 'other',
            'icon'        => 'icon.png',
            'psr4'        => ["Plugin\\{$pascal}\\" => 'app/'],
            'lifecycle'   => "Plugin\\{$pascal}\\hooks\\Lifecycle",
            'routes'      => ['tenantapi' => 'app/tenantapi/route.php'],
            'tenant'      => $tenant,
        ];
        if (!empty($config['with_uniapp'])) {
            $manifest['uniapp'] = [
                'subpackage' => 'modules/' . $code,
                'pages' => [
                    ['path' => 'pages/index/index', 'title' => (string) $config['name']],
                ],
            ];
        }
        return $manifest;
    }

    private function put(string $path, string $contents, array &$created): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new BusinessException("无法创建目录：{$dir}", 500);
        }
        if (file_put_contents($path, $contents) === false) {
            throw new BusinessException("文件写入失败：{$path}", 500);
        }
        $created[] = $path;
    }

    private function pascal(string $code): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $code)));
    }

    private function snake(string $code): string
    {
        return str_replace('-', '_', $code);
    }

    private function writeIcon(string $dir, array &$created): void
    {
        // 1x1 透明 PNG 占位图（合法 PNG，通过扩展名 + 魔数校验）
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $this->put($dir . '/icon.png', (string) $png, $created);
    }

    /** @param array<string,mixed> $config */
    private function readmeTpl(array $config): string
    {
        $name = (string) $config['name'];
        $code = (string) $config['code'];
        $kind = (string) $config['kind'];
        return <<<MD
        # {$name}

        - code: `{$code}`
        - kind: `{$kind}`

        由 `php think plugin:create` 生成。打包：`php think plugin:pack {$code}`。

        ## database/ 目录

        - `install.sql` — 全新安装表结构，须始终反映最新完整结构（发版含 schema 变更时同步更新）
        - `uninstall.sql` — purge 时执行的清理数据 SQL（不可逆）
        - `testdata.sql` — 演示数据（仅 INSERT/UPDATE，`{TENANT_ID}` 占位符）
        - `updates/vX.Y.Z.sql` — 版本增量升级 SQL，按目标版本号命名
        MD . "\n";
    }

    private function lifecycleTpl(string $pascal): string
    {
        return <<<PHP
        <?php
        declare(strict_types=1);

        namespace Plugin\\{$pascal}\\hooks;

        use core\\plugin\\contracts\\LifecycleHook;

        class Lifecycle implements LifecycleHook
        {
            public function install(): void {}
            public function uninstall(): void {}
            public function upgrade(string \$from, string \$to): void {}
            public function enable(int \$tenantId): void {}
            public function disable(int \$tenantId): void {}
        }
        PHP . "\n";
    }

    private function serviceTpl(string $pascal): string
    {
        return <<<PHP
        <?php
        declare(strict_types=1);

        namespace Plugin\\{$pascal};

        class {$pascal}Service
        {
            public function greet(string \$name): string
            {
                return "Hello, {\$name}!";
            }
        }
        PHP . "\n";
    }

    private function routeTpl(string $code): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        // {$code} 租户端路由占位。在此用 \\think\\facade\\Route 注册业务路由，
        // 中间件链由 PluginLoader 自动包裹（tenant_context/auth/status/entitlement/permission）。
        PHP . "\n";
    }

    private function installSqlTpl(string $code): string
    {
        $table = $this->snake($code) . '_items';
        return <<<SQL
        -- {$code} 全新安装表结构（发版含 DB 变更时：更新本文件 + 新增 updates/vX.Y.Z.sql）
        CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tenant_id` int unsigned NOT NULL DEFAULT 0,
            `name` varchar(200) NOT NULL DEFAULT '',
            `created_at` datetime NULL,
            PRIMARY KEY (`id`),
            KEY `idx_tenant_id` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
        SQL . "\n";
    }

    private function uninstallSqlTpl(string $code): string
    {
        $table = $this->snake($code) . '_items';
        return "-- 清理数据（purge 时执行，不可逆）\nDROP TABLE IF EXISTS `{$table}`;\n";
    }

    private function testdataSqlTpl(string $code): string
    {
        $table = $this->snake($code) . '_items';
        return <<<SQL
        -- 演示数据（可选）：仅允许 INSERT/UPDATE，{TENANT_ID} 会被替换为导入租户的 ID
        INSERT INTO `{$table}` (`tenant_id`, `name`, `created_at`) VALUES ({TENANT_ID}, '示例数据', NOW());
        SQL . "\n";
    }

    /** @param array<string,mixed> $config */
    private function vueTpl(array $config): string
    {
        $name = (string) $config['name'];
        return <<<VUE
        <script setup lang="ts">
        // {$name} 列表页（脚手架占位）。请按 useListPage() 约定补充实际逻辑。
        </script>

        <template>
          <div class="p-4">
            <h2>{$name}</h2>
            <p>由 plugin:create 生成的占位页面。</p>
          </div>
        </template>
        VUE . "\n";
    }

    private function apiTpl(string $code): string
    {
        $base = '/tenantapi/' . $code;
        return <<<TS
        import { myRequest } from '@/utils/request'

        // {$code} 接口占位。后端路由在 app/tenantapi/route.php 注册。
        export function fetchList(params: Record<string, unknown>) {
          return myRequest.get('{$base}/list', { params })
        }
        TS . "\n";
    }

    /** @param array<string,mixed> $config */
    private function uniappTpl(array $config): string
    {
        $name = (string) $config['name'];
        return <<<VUE
        <script setup lang="ts">
        // {$name} 移动端首页（脚手架占位）。
        </script>

        <template>
          <view class="page">
            <text>{$name}</text>
          </view>
        </template>
        VUE . "\n";
    }
}
