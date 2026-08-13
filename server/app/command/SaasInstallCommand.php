<?php
declare(strict_types=1);

namespace app\command;

use core\database\SqlRunner;
use core\runtime\RuntimePaths;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class SaasInstallCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:install')
            ->setDescription('Interactive installer for YdAdmin SaaS')
            ->addOption('docker', null, Option::VALUE_NONE, 'Use Docker defaults (mysql/redis hostnames)')
            ->addOption('non-interactive', null, Option::VALUE_NONE, 'Use defaults without prompting');
    }

    protected function execute(Input $input, Output $output): int
    {
        $output->writeln('<info>YdAdmin SaaS Installer v2.0.0</info>');
        $output->writeln(str_repeat('-', 40));
        $output->writeln('');

        $isDocker = $input->getOption('docker');
        $isNonInteractive = $input->getOption('non-interactive');

        $envPath = $this->app->getRootPath() . '.env';
        $examplePath = $this->app->getRootPath() . '.env.example';

        if (!file_exists($examplePath)) {
            $output->writeln('<error>.env.example not found!</error>');
            return 1;
        }

        // [1/4] Database
        $output->writeln('<comment>[1/4] Database Configuration</comment>');
        $dbHost = $isDocker ? 'mysql' : ($isNonInteractive ? '127.0.0.1' : $this->askDefault($output, 'MySQL Host', '127.0.0.1'));
        $dbPort = $isNonInteractive ? '3306' : $this->askDefault($output, 'MySQL Port', '3306');
        $dbName = $isNonInteractive ? 'ydadmin_saas' : $this->askDefault($output, 'Database Name', 'ydadmin_saas');
        $dbUser = $isNonInteractive ? 'root' : $this->askDefault($output, 'Username', 'root');
        $dbPass = $isNonInteractive ? 'root' : $this->askDefault($output, 'Password', 'root');
        $output->writeln('');

        // [2/4] Redis
        $output->writeln('<comment>[2/4] Redis Configuration</comment>');
        $redisHost = $isDocker ? 'redis' : ($isNonInteractive ? '127.0.0.1' : $this->askDefault($output, 'Redis Host', '127.0.0.1'));
        $redisPort = $isNonInteractive ? '6379' : $this->askDefault($output, 'Redis Port', '6379');
        $redisPass = $isNonInteractive ? '' : $this->askDefault($output, 'Redis Password (empty for none)', '');
        $output->writeln('');

        // [3/4] SaaS domains
        $output->writeln('<comment>[3/4] SaaS Domain Configuration</comment>');
        $rootDomain = $isNonInteractive ? 'app.com' : $this->askDefault($output, 'Root Domain', 'app.com');
        $platformDomain = $isNonInteractive ? "admin.{$rootDomain}" : $this->askDefault($output, 'Platform Domain', "admin.{$rootDomain}");
        $output->writeln('');

        // [4/4] Security keys
        $output->writeln('<comment>[4/4] Security</comment>');
        $appKey = bin2hex(random_bytes(16));
        $jwtTenantSecret = bin2hex(random_bytes(32));
        $jwtPlatformSecret = bin2hex(random_bytes(32));
        $marketplaceKey = bin2hex(random_bytes(32));
        $output->writeln('  Generating APP_KEY... <info>done</info>');
        $output->writeln('  Generating JWT secrets... <info>done</info>');
        $output->writeln('');

        // Write .env
        $envContent = file_get_contents($examplePath);
        // 顶层键（占位符替换）
        $replacements = [
            'APP_KEY = change-me-app-key'                                              => "APP_KEY = {$appKey}",
            'JWT_TENANT_SECRET = change-me-tenant-secret-must-be-32-bytes-or-longer'   => "JWT_TENANT_SECRET = {$jwtTenantSecret}",
            'JWT_PLATFORM_SECRET = change-me-platform-secret-must-be-32-bytes-or-longer' => "JWT_PLATFORM_SECRET = {$jwtPlatformSecret}",
        ];

        foreach ($replacements as $search => $replace) {
            $envContent = str_replace($search, $replace, $envContent);
        }

        // Section-aware replacements（域名在 [SAAS] 段、键名不带 SAAS_ 前缀）
        $envContent = $this->replaceSectionValue($envContent, 'DB', 'NAME', $dbName);
        $envContent = $this->replaceSectionValue($envContent, 'DB', 'HOST', $dbHost);
        $envContent = $this->replaceSectionValue($envContent, 'DB', 'PORT', $dbPort);
        $envContent = $this->replaceSectionValue($envContent, 'DB', 'USER', $dbUser);
        $envContent = $this->replaceSectionValue($envContent, 'DB', 'PASS', $dbPass);
        $envContent = $this->replaceSectionValue($envContent, 'REDIS', 'HOST', $redisHost);
        $envContent = $this->replaceSectionValue($envContent, 'REDIS', 'PORT', $redisPort);
        $envContent = $this->replaceSectionValue($envContent, 'REDIS', 'PASSWORD', $redisPass);
        $envContent = $this->replaceSectionValue($envContent, 'SAAS', 'ROOT_DOMAIN', $rootDomain);
        $envContent = $this->replaceSectionValue($envContent, 'SAAS', 'PLATFORM_DOMAIN', $platformDomain);
        $envContent = $this->replaceSectionValue($envContent, 'SAAS', 'MARKETPLACE_ENCRYPTION_KEY', $marketplaceKey);

        if ($isDocker) {
            $envContent = $this->replaceSectionValue($envContent, 'QUEUE', 'CONNECTOR', 'redis');
            $envContent = $this->replaceSectionValue($envContent, 'CACHE', 'DRIVER', 'redis');
        }

        file_put_contents($envPath, $envContent);
        $output->writeln('<info>  .env file generated</info>');

        // Verify DB
        try {
            new \PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass, [\PDO::ATTR_TIMEOUT => 5]);
            $output->writeln('<info>  Database connection verified</info>');
        } catch (\PDOException $e) {
            $output->writeln("<error>  Database connection failed: {$e->getMessage()}</error>");
            return 1;
        }

        // Import schema + init data（不再依赖 think-migration；与 web 安装程序统一走 schema.sql）
        $dataDir = $this->app->getRootPath() . 'public/install/data/';
        $schemaSqlPath = $dataDir . 'schema.sql';
        if (!file_exists($schemaSqlPath)) {
            $output->writeln('<error>  Schema file not found: public/install/data/schema.sql</error>');
            return 1;
        }
        try {
            $pdo = new \PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            // CLI 安装不设置表前缀，与 init.sql 的裸表名保持一致
            $runner = new SqlRunner($pdo, '');

            $output->writeln('  Importing schema...');
            $runner->runFile($schemaSqlPath);
            $output->writeln('<info>  Schema imported</info>');

            $initSqlPath = $dataDir . 'init.sql';
            if (file_exists($initSqlPath)) {
                $runner->runFile($initSqlPath);
                $output->writeln('<info>  Initial data imported</info>');
            }

            $this->seedUpgradeBaseline($pdo);
        } catch (\Throwable $e) {
            $output->writeln("<error>  Data import failed: {$e->getMessage()}</error>");
            return 1;
        }

        // 可写目录（含 mobile-builds/_keys）；root 安装时尽量 chown 给 www
        $runtimeUser = (function_exists('posix_geteuid') && posix_geteuid() === 0) ? 'www' : null;
        $runtime = RuntimePaths::ensure($this->app->getRootPath(), $runtimeUser);
        if ($runtime['not_writable'] !== []) {
            $list = implode(', ', $runtime['not_writable']);
            $output->writeln("<comment>  Warning: directories not writable: {$list}</comment>");
            $output->writeln('  Fix with: <comment>php think saas:ensure-runtime --user=www</comment>');
        } else {
            $output->writeln('<info>  Runtime directories ensured</info>');
        }

        $output->writeln('');
        $output->writeln('<info>Installation complete!</info>');
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln("  1. Create platform admin:  <comment>php think saas:create-platform-admin</comment>");
        $output->writeln("  2. Fix runtime perms (Baota): <comment>php think saas:ensure-runtime --user=www</comment>");
        $output->writeln("  3. Start dev server:       <comment>php think run -H 0.0.0.0 -p 8005</comment>");

        return 0;
    }

    /**
     * 全新安装后把 database/updates 下所有历史版本标记为已应用，
     * 使后续 php think yd:update 只执行新增版本增量。
     */
    private function seedUpgradeBaseline(\PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_upgrades` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `version` varchar(20) NOT NULL, `applied_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_version` (`version`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='框架数据库升级记录'");

        $dir = $this->app->getRootPath() . 'database/updates';
        if (!is_dir($dir)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT IGNORE INTO `system_upgrades` (`version`, `applied_at`) VALUES (?, ?)");
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($dir . '/' . $entry)) {
                continue;
            }
            if (!preg_match('/^v?(\d+\.\d+\.\d+)$/', $entry, $m)) {
                continue;
            }
            $stmt->execute([$m[1], $now]);
        }
    }

    private function askDefault(Output $output, string $question, string $default): string
    {
        $output->write("  {$question} [{$default}]: ");
        $value = trim(fgets(STDIN) ?: '');
        return $value === '' ? $default : $value;
    }

    private function replaceSectionValue(string $content, string $section, string $key, string $value): string
    {
        $lines = explode("\n", $content);
        $inSection = false;
        foreach ($lines as $i => $line) {
            if (trim($line) === "[{$section}]") {
                $inSection = true;
                continue;
            }
            if ($inSection && str_starts_with(trim($line), '[')) {
                $inSection = false;
            }
            if ($inSection && preg_match('/^' . preg_quote($key, '/') . '\s*=/', trim($line))) {
                $lines[$i] = "{$key} = {$value}";
            }
        }
        return implode("\n", $lines);
    }
}
