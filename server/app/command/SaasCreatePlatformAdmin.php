<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\repository\saas\PlatformAdminRepository;

/**
 * 创建平台超管账号
 *
 * 用法：
 *   php think saas:create-platform-admin <username> <password> [email]
 *
 * 设计要点：
 *   1. password 在此处用 password_hash(PASSWORD_DEFAULT) 加盐，绕开 init.sql 预置的坏 hash 问题
 *   2. 重复 username 直接失败并返回 exit 1
 *   3. status 默认 1（启用）
 *   4. PlatformAdminRepository 禁用了 tenantScoped，可在 CLI 无 TenantContext 场景下直接调用
 */
class SaasCreatePlatformAdmin extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:create-platform-admin')
             ->addArgument('username', Argument::REQUIRED, '用户名')
             ->addArgument('password', Argument::REQUIRED, '密码（明文，命令内部会 hash）')
             ->addArgument('email',    Argument::OPTIONAL, '邮箱（可选）', '')
             ->setDescription('创建平台超管账号（SaaS 平台侧）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $username = (string) $input->getArgument('username');
        $password = (string) $input->getArgument('password');
        $email    = (string) $input->getArgument('email');

        if (strlen($username) < 2 || strlen($username) > 50) {
            $output->error('用户名长度必须在 2-50 之间');
            return 1;
        }
        if (strlen($password) < 6 || strlen($password) > 100) {
            $output->error('密码长度必须在 6-100 之间');
            return 1;
        }

        $repo = new PlatformAdminRepository();

        if ($repo->findByUsername($username)) {
            $output->error("账号 {$username} 已存在");
            return 1;
        }

        $repo->create([
            'username'  => $username,
            'password'  => password_hash($password, PASSWORD_DEFAULT),
            'real_name' => $username,
            'email'     => $email,
            'status'    => 1,
            // 平台超管走 is_super 旁路鉴权（无需角色）；与 web 安装器(install.class.php)保持一致
            'is_super'  => 1,
        ]);

        $output->info("平台超管 {$username} 创建成功");
        return 0;
    }
}
