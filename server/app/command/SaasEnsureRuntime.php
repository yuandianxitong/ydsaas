<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\command;

use core\runtime\RuntimePaths;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 创建/修复 runtime 与 public/storage 可写目录权限。
 *
 * 用法：
 *   php think saas:ensure-runtime
 *   php think saas:ensure-runtime --user=www
 *
 * 在 root 下执行并指定 --user 时会尝试 chown；宝塔部署后建议跑一次。
 */
class SaasEnsureRuntime extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:ensure-runtime')
            ->addOption('user', 'u', Option::VALUE_OPTIONAL, 'PHP-FPM 运行用户（root 执行时 chown 目标）', 'www')
            ->setDescription('确保 runtime/storage 目录存在且可写（修复 root:755 导致 www 无法写入）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $root = rtrim($this->app->getRootPath(), '/\\') . '/';
        $user = (string) $input->getOption('user');
        if ($user === '') {
            $user = 'www';
        }

        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
        $output->writeln('<info>Ensuring runtime directories under:</info> ' . $root);
        if ($isRoot) {
            $output->writeln("<comment>Running as root → will chown to {$user} when possible</comment>");
        } else {
            $output->writeln('<comment>Not root → mkdir/chmod only（若仍不可写请用 root 加 --user 重跑）</comment>');
        }

        $result = RuntimePaths::ensure($root, $isRoot ? $user : null);

        foreach ($result['created'] as $rel) {
            $output->writeln("  <info>+</info> created {$rel}");
        }
        foreach ($result['chowned'] as $rel) {
            $output->writeln("  <info>~</info> chown {$user} {$rel}");
        }
        foreach ($result['errors'] as $err) {
            $output->writeln("  <error>!</error> {$err}");
        }

        if ($result['not_writable'] !== []) {
            $list = implode(', ', $result['not_writable']);
            $output->writeln('');
            $output->writeln("<error>以下目录对当前进程仍不可写：{$list}</error>");
            $output->writeln('请用 root 执行：');
            $output->writeln("  <comment>cd {$root} && php think saas:ensure-runtime --user={$user}</comment>");
            $output->writeln('或：');
            $output->writeln("  <comment>chown -R {$user}:{$user} runtime public/storage && chmod -R 775 runtime public/storage</comment>");
            return 1;
        }

        $output->writeln('');
        $output->writeln('<info>OK — runtime/storage directories are writable.</info>');
        return 0;
    }
}
