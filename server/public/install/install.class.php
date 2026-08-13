<?php
/* ============================================================
 * 项目：元点Saas
 * 安装程序：系统初始化向导
 * ============================================================ */

// 复用框架内的 SQL 执行器（前缀改写 / 语句拆分 / 占位符替换），
// 保证「安装」与「php think yd:update 升级」的前缀处理行为完全一致。
require_once dirname(dirname(__DIR__)) . '/core/database/SqlRunner.php';
require_once dirname(dirname(__DIR__)) . '/core/runtime/RuntimePaths.php';

class Installer
{
    private $rootPath;

    public function __construct()
    {
        $this->rootPath = dirname(dirname(__DIR__)) . '/';
    }

    /**
     * 检查运行环境
     */
    public function checkEnvironment()
    {
        $requirements = [
            'php_version' => [
                'name' => 'PHP版本',
                'required' => '>= 8.0.0',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
                'type' => 'critical'
            ],
            'pdo' => [
                'name' => 'PDO扩展',
                'required' => '必需',
                'current' => extension_loaded('pdo') ? '已安装' : '未安装',
                'status' => extension_loaded('pdo'),
                'type' => 'critical'
            ],
            'pdo_mysql' => [
                'name' => 'PDO_MySQL扩展',
                'required' => '必需',
                'current' => extension_loaded('pdo_mysql') ? '已安装' : '未安装',
                'status' => extension_loaded('pdo_mysql'),
                'type' => 'critical'
            ],
            'mbstring' => [
                'name' => 'mbstring扩展',
                'required' => '必需',
                'current' => extension_loaded('mbstring') ? '已安装' : '未安装',
                'status' => extension_loaded('mbstring'),
                'type' => 'critical'
            ],
            'json' => [
                'name' => 'JSON扩展',
                'required' => '必需',
                'current' => extension_loaded('json') ? '已安装' : '未安装',
                'status' => extension_loaded('json'),
                'type' => 'critical'
            ],
            'fileinfo' => [
                'name' => 'fileinfo扩展',
                'required' => '必需',
                'current' => extension_loaded('fileinfo') ? '已安装' : '未安装',
                'status' => extension_loaded('fileinfo'),
                'type' => 'critical'
            ],
            'curl' => [
                'name' => 'cURL扩展',
                'required' => '推荐',
                'current' => extension_loaded('curl') ? '已安装' : '未安装',
                'status' => extension_loaded('curl'),
                'type' => 'recommended'
            ],
            'openssl' => [
                'name' => 'OpenSSL扩展',
                'required' => '推荐',
                'current' => extension_loaded('openssl') ? '已安装' : '未安装',
                'status' => extension_loaded('openssl'),
                'type' => 'recommended'
            ],
            'gd' => [
                'name' => 'GD扩展',
                'required' => '推荐',
                'current' => extension_loaded('gd') ? '已安装' : '未安装',
                'status' => extension_loaded('gd'),
                'type' => 'recommended'
            ],
            'redis' => [
                'name' => 'Redis扩展',
                'required' => '推荐（缓存/队列）',
                'current' => extension_loaded('redis') ? '已安装' : '未安装',
                'status' => extension_loaded('redis'),
                'type' => 'recommended'
            ]
        ];

        // 检查目录权限 - 修正路径为与public同级
        $directories = [
            'runtime' => $this->rootPath . 'runtime',
            'config' => $this->rootPath . 'config',
            'public' => $this->rootPath . 'public'
        ];

        $permissions = [];
        foreach ($directories as $name => $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $readable = $exists && is_readable($path);

            $permissions[$name] = [
                'name' => $name . '目录',
                'path' => str_replace($this->rootPath, '', $path),
                'exists' => $exists,
                'writable' => $writable,
                'readable' => $readable,
                'status' => $exists && $writable && $readable
            ];
        }

        // 判断整体状态
        $criticalPassed = true;
        $warningCount = 0;

        foreach ($requirements as $req) {
            if (!$req['status']) {
                if ($req['type'] === 'critical') {
                    $criticalPassed = false;
                } else {
                    $warningCount++;
                }
            }
        }

        foreach ($permissions as $perm) {
            if (!$perm['status']) {
                $criticalPassed = false;
            }
        }

        return [
            'success' => true,
            'requirements' => $requirements,
            'permissions' => $permissions,
            'critical_passed' => $criticalPassed,
            'warning_count' => $warningCount,
            'can_continue' => true // 允许继续安装，即使有警告
        ];
    }

    /**
     * 测试数据库连接
     */
    public function testDatabase($config)
    {
        $host = $config['db_host'] ?? '';
        $port = $config['db_port'] ?? 3306;
        $database = $config['db_name'] ?? '';
        $username = $config['db_user'] ?? '';
        $password = $config['db_pass'] ?? '';

        if (empty($host) || empty($database) || empty($username)) {
            return ['success' => false, 'message' => '数据库信息不完整'];
        }

        try {
            // 先连接服务器
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci",
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // 检查数据库是否存在，不存在则创建
            $stmt = $pdo->query("SHOW DATABASES LIKE '{$database}'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("CREATE DATABASE `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // 切换到目标数据库
            $pdo->exec("USE `{$database}`");

            return ['success' => true, 'message' => '数据库连接成功'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => '数据库连接失败: ' . $e->getMessage()];
        }
    }

    /**
     * 开始安装
     */
    public function startInstall($config)
    {
        try {
            $prefix = trim((string)($config['db_prefix'] ?? ''));
            if ($prefix !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
                return ['success' => false, 'message' => '数据表前缀仅允许字母、数字、下划线'];
            }

            $_SESSION['install_config'] = $config;
            $_SESSION['install_step'] = 0;
            $_SESSION['install_total'] = 6;
            $_SESSION['install_status'] = 'running';
            $_SESSION['install_message'] = '开始安装...';

            return ['success' => true, 'message' => '安装已开始'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '安装启动失败: ' . $e->getMessage()];
        }
    }

    /**
     * 获取安装进度
     */
    public function getInstallProgress()
    {
        if (!isset($_SESSION['install_status'])) {
            return ['success' => false, 'message' => '安装未开始'];
        }

        $total = $_SESSION['install_total'] ?? 7;

        if ($_SESSION['install_status'] === 'running') {
            $this->processInstallStep();
        }

        // 在 processInstallStep 之后重新读取，确保拿到最新值
        $step = $_SESSION['install_step'] ?? 0;
        $status = $_SESSION['install_status'];
        $message = $_SESSION['install_message'] ?? '';

        $percent = ($status === 'completed')
            ? 100
            : ($total > 0 ? round(($step / $total) * 100) : 0);

        $stepDefs = [
            ['key' => 'step_0', 'name' => '更新配置文件'],
            ['key' => 'step_1', 'name' => '生成系统密钥'],
            ['key' => 'step_2', 'name' => '创建数据表'],
            ['key' => 'step_3', 'name' => '初始化数据'],
            ['key' => 'step_4', 'name' => '创建目录'],
            ['key' => 'step_5', 'name' => '完成安装'],
        ];

        return [
            'success'  => true,
            'status'   => $status,
            'step'     => $step,
            'total'    => $total,
            'percent'  => $percent,
            'message'  => $message,
            'step_key' => 'step_' . min($step, count($stepDefs) - 1),
            'steps'    => $stepDefs,
        ];
    }

    /**
     * 处理安装步骤
     */
    private function processInstallStep()
    {
        $step = $_SESSION['install_step'];
        $config = $_SESSION['install_config'];

        try {
            switch ($step) {
                case 0:
                    $this->updateEnvFile($config);
                    $_SESSION['install_message'] = '配置文件更新完成';
                    break;

                case 1:
                    $this->generateAuthKey();
                    $_SESSION['install_message'] = '系统密钥生成完成';
                    break;

                case 2:
                    $this->createDatabaseTables();
                    $_SESSION['install_message'] = '数据表创建完成';
                    break;

                case 3:
                    $this->insertInitialData($config);
                    $_SESSION['install_message'] = '初始化数据完成';
                    break;

                case 4:
                    $this->createDirectories();
                    $_SESSION['install_message'] = '目录创建完成';
                    break;

                case 5:
                    $this->createInstallLock();
                    $_SESSION['install_message'] = '安装完成';
                    $_SESSION['install_status'] = 'completed';
                    break;

                default:
                    $_SESSION['install_status'] = 'completed';
                    return;
            }

            $_SESSION['install_step']++;

        } catch (Exception $e) {
            $_SESSION['install_status'] = 'error';
            $_SESSION['install_message'] = '安装失败: ' . $e->getMessage();
        }
    }

    /**
     * 更新环境配置文件
     */
    private function updateEnvFile($config)
    {
        $envFile = $this->rootPath . '.env';
        // 模板文件名是 .env.example（此前误写成 .example.env，导致 .env 缺失时无法从模板复制）
        $envExample = $this->rootPath . '.env.example';

        // 如果 .env 不存在，从模板整份复制（含顶层 JWT 占位符与 [SAAS] 段，后续按需覆盖）
        if (!file_exists($envFile) && file_exists($envExample)) {
            copy($envExample, $envFile);
        }

        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

        // ---- 数据库配置写入 [DB] 段 ----
        // ThinkPHP 的 env() 通过 parse_ini_file 读取，
        // [DB] 段下的 NAME = xxx 会被解析为 DB_NAME，匹配 env('DB_NAME')
        $dbSection = [
            'TYPE'    => 'mysql',
            'HOST'    => $config['db_host'],
            'NAME'    => $config['db_name'],
            'USER'    => $config['db_user'],
            'PASS'    => $config['db_pass'],
            'PORT'    => $config['db_port'],
            'CHARSET' => 'utf8mb4',
            'PREFIX'  => trim((string)($config['db_prefix'] ?? ''))
        ];

        // 如果已有 [DB] 段，替换整段；否则追加
        $dbBlock = "[DB]\n";
        foreach ($dbSection as $k => $v) {
            $dbBlock .= "{$k} = {$v}\n";
        }

        if (preg_match('/^\[DB\]\s*$/m', $envContent)) {
            // 替换 [DB] 段（从 [DB] 到下一个 [SECTION] 或文件末尾）
            $envContent = preg_replace(
                '/^\[DB\]\s*\n(?:(?!\[).+\n?)*/m',
                $dbBlock,
                $envContent
            );
        } else {
            $envContent = rtrim($envContent) . "\n\n" . $dbBlock;
        }

        // ---- SaaS 域名写入 [SAAS] 段 ----
        // 键名不带 SAAS_ 前缀：ThinkPHP 分节加载会自动补 SAAS_，
        // 故 [SAAS] 段的 PLATFORM_DOMAIN 对应 env('SAAS_PLATFORM_DOMAIN')。
        [$platformDomain, $rootDomain] = $this->resolveInstallDomains($config);
        if ($platformDomain !== '') {
            $envContent = $this->setEnvSectionValue($envContent, 'SAAS', 'PLATFORM_DOMAIN', $platformDomain);
        }
        if ($rootDomain !== '') {
            $envContent = $this->setEnvSectionValue($envContent, 'SAAS', 'ROOT_DOMAIN', $rootDomain);
        }

        file_put_contents($envFile, $envContent);
    }

    /**
     * 解析安装时的平台/根域名：优先用表单字段，缺失时从当前访问 Host 兜底推导。
     * @return array{0:string,1:string} [platformDomain, rootDomain]
     */
    private function resolveInstallDomains(array $config): array
    {
        $platformDomain = strtolower(trim((string)($config['platform_domain'] ?? '')));
        $rootDomain     = strtolower(trim((string)($config['root_domain'] ?? '')));

        if ($platformDomain === '') {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $platformDomain = strtolower(explode(':', (string)$host)[0]);
        }
        // 去掉端口
        $platformDomain = explode(':', $platformDomain)[0];

        if ($rootDomain === '' && $platformDomain !== '' && !filter_var($platformDomain, FILTER_VALIDATE_IP)) {
            $parts = explode('.', $platformDomain);
            $rootDomain = count($parts) > 2 ? implode('.', array_slice($parts, 1)) : $platformDomain;
        }

        return [$platformDomain, $rootDomain];
    }

    /**
     * 判断某键当前是否需要生成：值为空或仍是占位符（change-me）时返回 true。
     * 避免在已存在有效密钥的 .env 上重复覆盖。
     */
    private function envValueNeedsGenerate(string $content, string $key): bool
    {
        // 注意：`=` 两侧只吃水平空白 [ \t]，不能用 \s（含换行）。否则遇到空值行
        // `KEY =\n# 下一行注释` 时，\s* 会跨过换行把下一行内容当成 value，
        // 导致空值键被误判为「已有值、无需生成」（MARKETPLACE_ENCRYPTION_KEY 曾因此始终为空）。
        if (preg_match('/^' . preg_quote($key, '/') . '[ \t]*=[ \t]*(.*)$/m', $content, $m)) {
            $val = trim($m[1]);
            return $val === '' || stripos($val, 'change-me') !== false;
        }
        return true; // 键不存在也需要生成
    }

    /**
     * 顶层键设置（第一个 [SECTION] 之前）。存在则替换首个出现，否则插到首个分节前。
     */
    private function setEnvTopLevelValue(string $content, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '\s*=.*$/m';
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, "{$key} = {$value}", $content, 1);
        }
        if (preg_match('/^\[/m', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            return substr($content, 0, $pos) . "{$key} = {$value}\n" . substr($content, $pos);
        }
        return rtrim($content) . "\n{$key} = {$value}\n";
    }

    /**
     * 分节键设置。段内有该键则替换，段存在但缺键则插到段头之后，段不存在则追加新段。
     */
    private function setEnvSectionValue(string $content, string $section, string $key, string $value): string
    {
        $lines = explode("\n", $content);
        $sectionStart = -1;
        $inSection = false;

        foreach ($lines as $i => $line) {
            $t = trim($line);
            if ($t === "[{$section}]") {
                $inSection = true;
                $sectionStart = $i;
                continue;
            }
            if ($inSection && str_starts_with($t, '[')) {
                break; // 到达下一个段，段内未命中该键
            }
            if ($inSection && preg_match('/^' . preg_quote($key, '/') . '\s*=/', $t)) {
                $lines[$i] = "{$key} = {$value}";
                return implode("\n", $lines);
            }
        }

        if ($sectionStart === -1) {
            return rtrim($content) . "\n\n[{$section}]\n{$key} = {$value}\n";
        }
        array_splice($lines, $sectionStart + 1, 0, ["{$key} = {$value}"]);
        return implode("\n", $lines);
    }

    /**
     * 创建数据表
     */
    private function createDatabaseTables()
    {
        $config = $_SESSION['install_config'];
        $prefix = trim((string)($config['db_prefix'] ?? ''));

        $pdo = $this->createPdo($config);

        // Installer ships with its own schema file under public/install/data
        $schemaFile = INSTALL_PATH . 'data/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('数据库结构文件不存在: public/install/data/schema.sql');
        }

        // Guard against re-install on a dirty database.
        $existing = $this->listTablesWithPrefix($pdo, $prefix);
        if (!empty($existing)) {
            $sample = array_slice($existing, 0, 8);
            throw new Exception('检测到数据库中已存在同前缀的数据表（例如：' . implode(', ', $sample) . '）。请更换空数据库或使用新的表前缀。');
        }

        $this->executeSqlFile($pdo, $schemaFile, $prefix);

        // Sanity check: ensure core tables exist after schema import.
        $required = ['admins', 'roles', 'system_configs', 'platform_admins', 'platform_menus'];
        $missing = [];
        foreach ($required as $table) {
            if (!$this->tableExists($pdo, $prefix . $table)) {
                $missing[] = $prefix . $table;
            }
        }
        if (!empty($missing)) {
            throw new Exception('数据库结构导入不完整，缺少数据表: ' . implode(', ', $missing));
        }
    }

    /**
     * 插入初始数据
     */
    private function insertInitialData($config)
    {
        $pdo = $this->createPdo($config);

        $prefix = trim((string)($config['db_prefix'] ?? ''));
        $initFile = INSTALL_PATH . 'data/init.sql';
        if (!file_exists($initFile)) {
            throw new Exception('初始化数据文件不存在: public/install/data/init.sql');
        }
        $this->executeSqlFile($pdo, $initFile, $prefix);

        // 导入区域数据
        $regionsFile = INSTALL_PATH . 'data/regions.sql';
        if (file_exists($regionsFile)) {
            $this->executeSqlFile($pdo, $regionsFile, $prefix);
        }

        // 导入演示数据（文章等），替换 {{SITE_URL}} 为实际安装域名
        $demoFile = INSTALL_PATH . 'data/demo.sql';
        if (file_exists($demoFile)) {
            $siteUrl = $this->detectSiteUrl($config);
            $this->executeSqlFileWithReplace($pdo, $demoFile, $prefix, [
                '{{SITE_URL}}' => rtrim($siteUrl, '/'),
            ]);
        }

        $this->ensureBaseRole($pdo, $prefix);
        $this->upsertAdminAccount($pdo, $config, $prefix);
        $this->upsertPlatformAdmin($pdo, $config, $prefix);
        $this->seedPlatformMenus($pdo, $prefix);
        $this->seedPlatformRoles($pdo, $prefix);
        $this->upsertSystemConfig($pdo, $config, $prefix);
        $this->seedUpgradeBaseline($pdo, $prefix);
    }

    /**
     * 全新安装时把 database/updates 下所有历史版本标记为已应用，
     * 使 php think yd:update 之后只执行新版本增量，避免对全新库重复执行历史脚本。
     */
    private function seedUpgradeBaseline(PDO $pdo, string $prefix): void
    {
        $table = $this->wrapTable('system_upgrades', $prefix);
        if (!$this->tableExists($pdo, $prefix . 'system_upgrades')) {
            // schema.sql 应已创建该表；缺失时兜底创建，避免安装中断
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `version` varchar(20) NOT NULL, `applied_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_version` (`version`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='框架数据库升级记录'");
        }

        $dir = $this->rootPath . 'database/updates';
        if (!is_dir($dir)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($dir . '/' . $entry)) {
                continue;
            }
            if (!preg_match('/^v?(\d+\.\d+\.\d+)$/', $entry, $m)) {
                continue;
            }
            $stmt = $pdo->prepare("INSERT IGNORE INTO {$table} (`version`, `applied_at`) VALUES (?, ?)");
            $stmt->execute([$m[1], $now]);
        }
    }

    private function createPdo(array $config, bool $withDb = true): PDO
    {
        $host = (string)($config['db_host'] ?? '');
        $port = (string)($config['db_port'] ?? 3306);
        $dbName = (string)($config['db_name'] ?? '');
        $user = (string)($config['db_user'] ?? '');
        $pass = (string)($config['db_pass'] ?? '');

        $dsn = $withDb
            ? "mysql:host={$host};dbname={$dbName};port={$port};charset=utf8mb4"
            : "mysql:host={$host};port={$port};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Some environments ignore DSN charset; force it.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci",
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }

    private function ensureBaseRole(PDO $pdo, string $prefix)
    {
        $table = $this->wrapTable('roles', $prefix);
        $exists = $this->recordExists($pdo, $table, 'id', 1);

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO {$table} (id, name, title, description, data_scope, is_system, status, sort, created_at, updated_at) VALUES (1, ?, ?, ?, 1, 1, 1, 0, ?, ?)");
        $stmt->execute(['super_admin', '超级管理员', '系统超级管理员，拥有所有权限', $now, $now]);
    }

    private function upsertAdminAccount(PDO $pdo, array $config, string $prefix)
    {
        $username = trim((string)($config['admin_username'] ?? ''));
        $password = (string)($config['admin_password'] ?? '');
        if ($username === '' || $password === '') {
            throw new Exception('管理员账号或密码为空');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $email = trim((string)($config['admin_email'] ?? ''));
        $nickname = trim((string)($config['admin_nickname'] ?? $username));
        $now = date('Y-m-d H:i:s');

        $adminTable = $this->wrapTable('admins', $prefix);
        $adminRoleTable = $this->wrapTable('admin_roles', $prefix);

        $exists = $this->recordExists($pdo, $adminTable, 'id', 1);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE {$adminTable} SET username = ?, password = ?, nickname = ?, email = ?, status = 1, updated_at = ? WHERE id = 1");
            $stmt->execute([$username, $passwordHash, $nickname, $email, $now]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$adminTable} (id, username, email, password, nickname, status, created_at, updated_at) VALUES (1, ?, ?, ?, ?, 1, ?, ?)");
            $stmt->execute([$username, $email, $passwordHash, $nickname, $now, $now]);
        }

        $roleLinkExists = $this->recordExists($pdo, $adminRoleTable, 'admin_id', 1, 'role_id', 1);
        if (!$roleLinkExists) {
            $stmt = $pdo->prepare("INSERT INTO {$adminRoleTable} (admin_id, role_id, created_at, updated_at) VALUES (1, 1, ?, ?)");
            $stmt->execute([$now, $now]);
        }
    }

    /**
     * SaaS: 在 platform_admins 表创建平台超管账号
     *
     * 安装时用户输入的管理员用户名密码同时写入 platform_admins（平台超管登录用）
     * 和 admins（租户后台保持原 upstream 兼容）。
     */
    private function upsertPlatformAdmin(PDO $pdo, array $config, string $prefix)
    {
        $username = trim((string)($config['admin_username'] ?? ''));
        $password = (string)($config['admin_password'] ?? '');
        if ($username === '' || $password === '') {
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $email = trim((string)($config['admin_email'] ?? ''));
        $now = date('Y-m-d H:i:s');

        $table = $this->wrapTable('platform_admins', $prefix);

        $exists = $this->recordExists($pdo, $table, 'id', 1);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE {$table} SET username = ?, password = ?, email = ?, status = 1, is_super = 1, updated_at = ? WHERE id = 1");
            $stmt->execute([$username, $passwordHash, $email, $now]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO {$table} (id, username, password, real_name, email, status, is_super, created_at, updated_at) VALUES (1, ?, ?, ?, ?, 1, 1, ?, ?)");
            $stmt->execute([$username, $passwordHash, $username, $email, $now, $now]);
        }
    }

    /**
     * SaaS: 在 platform_menus 表插入平台后台菜单
     */
    private function seedPlatformMenus(PDO $pdo, string $prefix)
    {
        $table = $this->wrapTable('platform_menus', $prefix);

        // 已有数据就跳过
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $menus = [
            [1, 0, 'Dashboard',   'dashboard',     'dashboard/index',     'i-svg:house',        1, '',                2, 0, 1],
            [2, 0, '租户管理',     'tenant',        'tenant/index',        'i-svg:users-round',  2, 'tenant.view',    2, 0, 1],
            [3, 0, '套餐管理',     'plan',          'plan/index',          'i-svg:boxes',        3, 'plan.view',      2, 0, 1],
            [4, 0, '功能开关',     'plan-feature',  'plan-feature/index',  'i-svg:settings-2',   4, 'plan_feature.view', 2, 0, 1],
            [5, 0, '订单管理',     'order',         'order/index',         'i-svg:receipt-text', 5, 'order.view',     2, 0, 1],
        ];

        $sql = "INSERT INTO {$table} (id, parent_id, name, path, component, icon, sort, permission, type, hidden, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($menus as $row) {
            $stmt->execute(array_merge($row, [$now, $now]));
        }
    }

    /**
     * SaaS: 预置「平台管理员」角色并关联全部平台菜单
     */
    private function seedPlatformRoles(PDO $pdo, string $prefix)
    {
        $roleTable = $this->wrapTable('platform_roles', $prefix);
        $menuTable = $this->wrapTable('platform_menus', $prefix);
        $pivotTable = $this->wrapTable('platform_role_menus', $prefix);

        // 已有角色就跳过
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$roleTable}");
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 插入默认角色
        $stmt = $pdo->prepare("INSERT INTO {$roleTable} (id, name, code, description, sort, status, created_at, updated_at) VALUES (1, '平台管理员', 'platform_admin', '拥有全部平台菜单权限', 0, 1, ?, ?)");
        $stmt->execute([$now, $now]);

        // 关联全部平台菜单
        $menuIds = $pdo->query("SELECT id FROM {$menuTable}")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($menuIds)) {
            $sql = "INSERT INTO {$pivotTable} (platform_role_id, platform_menu_id) VALUES (1, ?)";
            $stmt = $pdo->prepare($sql);
            foreach ($menuIds as $menuId) {
                $stmt->execute([$menuId]);
            }
        }
    }

    private function upsertSystemConfig(PDO $pdo, array $config, string $prefix)
    {
        $table = $this->wrapTable('system_configs', $prefix);
        $siteName = trim((string)($config['site_name'] ?? ''));
        $siteUrl = trim((string)($config['site_url'] ?? ''));
        // 如果未传入 site_url，自动从当前请求获取
        if ($siteUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $siteUrl = $scheme . '://' . rtrim($host, '/');
        }
        $now = date('Y-m-d H:i:s');

        if ($siteName !== '') {
            $stmt = $pdo->prepare("INSERT INTO {$table} (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('site_name', ?, 'basic', 'string', '站点名称', '网站名称显示在浏览器标题栏', 1, 1, ?, ?) ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`), `updated_at` = VALUES(`updated_at`)");
            $stmt->execute([$siteName, $now, $now]);
        }

        if ($siteUrl !== '') {
            $stmt = $pdo->prepare("INSERT INTO {$table} (`config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('site_url', ?, 'basic', 'string', '站点网址', '网站访问地址', 2, 1, ?, ?) ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`), `updated_at` = VALUES(`updated_at`)");
            $stmt->execute([$siteUrl, $now, $now]);
        }
    }

    /**
     * 创建必要目录（与 core\runtime\RuntimePaths 同源列表）
     */
    private function createDirectories()
    {
        $directories = \core\runtime\RuntimePaths::relativeDirs();
        // web 安装跑在 PHP-FPM 用户下时不要 chown；仅 mkdir/chmod
        $result = \core\runtime\RuntimePaths::ensure($this->rootPath, null);
        $notWritable = $result['not_writable'];

        // 目录不可写时给出可操作报错（而不是稍后在写文件时抛出难懂的 Permission denied）
        if (!empty($notWritable)) {
            $list = implode(', ', $notWritable);
            throw new Exception(
                "以下目录不可写：{$list}。通常是被 root 或命令行用户预先创建导致属主与 PHP 运行用户不一致。"
                . "请在服务器 server 目录执行（把 www 换成实际 PHP-FPM 运行用户）："
                . "php think saas:ensure-runtime --user=www"
                . " 或 chown -R www:www runtime public/storage && chmod -R 775 runtime public/storage，然后重试安装。"
            );
        }

        // 复制演示资源（文章封面图片等）：public/storage 可写才复制，避免非致命失败中断安装
        $demoAssetsDir = INSTALL_PATH . 'data/demo-assets';
        if (is_dir($demoAssetsDir) && is_writable($this->rootPath . 'public/storage')) {
            $this->copyDirectory($demoAssetsDir, $this->rootPath . 'public/storage');
        }

        // 创建安全文件（防目录列举）：属于非关键加固，写不进去就跳过，绝不因此中断安装
        $indexContent = "<?php\n// Silence is golden.";
        foreach ($directories as $dir) {
            $path = $this->rootPath . $dir;
            $indexFile = $path . '/index.php';
            if (!file_exists($indexFile) && is_writable($path)) {
                @file_put_contents($indexFile, $indexContent);
            }
        }
    }

    /**
     * 生成应用密钥
     */
    private function generateAuthKey()
    {
        $this->getAuthKey(true);
    }

    /**
     * 创建安装锁定文件
     */
    private function createInstallLock()
    {
        $lockFile = $this->rootPath . 'config/install.lock';
        $lockContent = [
            'install_time' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'installer_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        // 确保config目录存在
        $configDir = dirname($lockFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($lockFile, json_encode($lockContent, JSON_PRETTY_PRINT));

        // If the project ran before installation (or DB_PREFIX changed during install),
        // ThinkPHP may still use cached config/route/schema files under runtime/.
        // Clear runtime cache/temp to ensure the new .env values take effect immediately.
        $this->clearRuntimeCaches();
    }

    private function clearRuntimeCaches(): void
    {
        $targets = [
            $this->rootPath . 'runtime/cache',
            $this->rootPath . 'runtime/temp',
        ];

        foreach ($targets as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            if (!is_writable($dir)) {
                continue; // 不可写目录跳过清理与加固，避免收尾步骤因权限报错中断安装
            }
            $this->deleteDirectoryChildren($dir, ['index.php']);
            // Keep runtime dirs safe from directory listing.
            $indexFile = rtrim($dir, '/') . '/index.php';
            if (!file_exists($indexFile)) {
                @file_put_contents($indexFile, "<?php\n// Silence is golden.");
            }
        }
    }

    private function deleteDirectoryChildren(string $dir, array $keepFiles = []): void
    {
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (in_array($item, $keepFiles, true)) {
                continue;
            }
            $path = rtrim($dir, '/') . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }
            @unlink($path);
        }
    }

    /**
     * 删除安装程序
     */
    public function deleteInstaller()
    {
        try {
            $installDir = __DIR__;
            $this->deleteDirectory($installDir);
            return ['success' => true, 'message' => '安装程序已删除'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => '删除失败: ' . $e->getMessage()];
        }
    }

    /**
     * 清除会话数据
     */
    public function clearSession()
    {
        // 清除安装相关的session数据
        $keys = ['install_config', 'install_step', 'install_total', 'install_status', 'install_message', 'install_auth_key'];
        foreach ($keys as $key) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }
        return ['success' => true, 'message' => '会话数据已清除'];
    }

    /**
     * 递归复制目录
     */
    private function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $items = scandir($src);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $dstPath);
            } else {
                copy($srcPath, $dstPath);
            }
        }
    }

    /**
     * 递归删除目录
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * 获取系统信息
     */
    public function getSystemInfo()
    {
        return [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS,
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];
    }

    /**
     * 获取安装时的站点URL
     */
    private function detectSiteUrl(array $config): string
    {
        $siteUrl = trim((string)($config['site_url'] ?? ''));
        if ($siteUrl !== '') {
            return $siteUrl;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        return $scheme . '://' . rtrim($host, '/');
    }

    /**
     * 执行SQL文件并替换占位符
     */
    private function executeSqlFileWithReplace(PDO $pdo, string $sqlFile, string $prefix, array $replacements): void
    {
        (new \core\database\SqlRunner($pdo, $prefix))->runFile($sqlFile, $replacements);
    }

    private function executeSqlFile(PDO $pdo, string $sqlFile, string $prefix = '')
    {
        (new \core\database\SqlRunner($pdo, $prefix))->runFile($sqlFile);
    }

    private function wrapTable(string $name, string $prefix): string
    {
        return '`' . $prefix . $name . '`';
    }

    private function recordExists(PDO $pdo, string $table, string $field, $value, ?string $field2 = null, $value2 = null): bool
    {
        if ($field2 !== null) {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$field} = ? AND {$field2} = ? LIMIT 1");
            $stmt->execute([$value, $value2]);
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$field} = ? LIMIT 1");
            $stmt->execute([$value]);
        }
        return (bool)$stmt->fetchColumn();
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tableName]);
        return (bool)$stmt->fetchColumn();
    }

    private function listTablesWithPrefix(PDO $pdo, string $prefix): array
    {
        if (trim($prefix) === '') {
            $stmt = $pdo->query('SHOW TABLES');
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            return array_values(array_filter(array_map(static fn($r) => (string)($r[0] ?? ''), $rows)));
        }

        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$prefix . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        return array_values(array_filter(array_map(static fn($r) => (string)($r[0] ?? ''), $rows)));
    }

    private function getAuthKey(bool $forceGenerate = false): string
    {
        if (!$forceGenerate && !empty($_SESSION['install_auth_key'])) {
            return (string)$_SESSION['install_auth_key'];
        }

        $envFile = $this->rootPath . '.env';
        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
        $authKey = '';

        if (preg_match("/^APP_KEY[ \t]*=[ \t]*(.*)$/m", $envContent, $matches)) {
            $authKey = trim($matches[1]);
        }

        if ($authKey === '' || $forceGenerate) {
            $authKey = bin2hex(random_bytes(32));
            $envContent = $this->setEnvTopLevelValue($envContent, 'APP_KEY', $authKey);

            // 生成双 JWT 密钥（platform / tenant 物理隔离，各 64 位 hex、互不相同），写在顶层
            // 框架读取 env('JWT_PLATFORM_SECRET') / env('JWT_TENANT_SECRET')，不再使用旧的单一 JWT_SECRET
            if ($this->envValueNeedsGenerate($envContent, 'JWT_TENANT_SECRET')) {
                $envContent = $this->setEnvTopLevelValue($envContent, 'JWT_TENANT_SECRET', bin2hex(random_bytes(32)));
            }
            if ($this->envValueNeedsGenerate($envContent, 'JWT_PLATFORM_SECRET')) {
                $envContent = $this->setEnvTopLevelValue($envContent, 'JWT_PLATFORM_SECRET', bin2hex(random_bytes(32)));
            }

            // 官方市场客户端密钥（[SAAS] 段，对应 env('SAAS_MARKETPLACE_ENCRYPTION_KEY')）
            if ($this->envValueNeedsGenerate($envContent, 'MARKETPLACE_ENCRYPTION_KEY')) {
                $envContent = $this->setEnvSectionValue($envContent, 'SAAS', 'MARKETPLACE_ENCRYPTION_KEY', bin2hex(random_bytes(32)));
            }

            file_put_contents($envFile, $envContent);
        }

        $_SESSION['install_auth_key'] = $authKey;
        return $authKey;
    }
}
