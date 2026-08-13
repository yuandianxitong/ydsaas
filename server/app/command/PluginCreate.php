<?php

/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use app\service\plugin\PluginScaffolder;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * plugin:create
 *
 * 交互式生成一个最小可用、kind 感知的插件骨架到 server/plugins/<code>/，
 * 产物开箱通过 PluginManifestValidator 与 PluginPackageInstaller::inspect。
 *
 * 用法：php think plugin:create [code] [--force]
 */
class PluginCreate extends Command
{
    private const CODE_REGEX = '/^[a-z][a-z0-9-]{1,79}$/';

    protected function configure(): void
    {
        $this->setName('plugin:create')
            ->setDescription('交互式生成一个新插件骨架到 plugins/<code>/')
            ->addArgument('code', Argument::OPTIONAL, '插件 code（kebab-case），缺省则提示输入')
            ->addOption('force', 'f', Option::VALUE_NONE, '覆盖已存在的插件目录');
    }

    protected function execute(Input $input, Output $output): int
    {
        $code = (string) ($input->getArgument('code') ?? '');

        // 非交互模式（管道/CI）必须直接给出合法 code，否则 ask() 会拿到 null 死循环
        if (!$input->isInteractive()) {
            if ($code === '' || !preg_match(self::CODE_REGEX, $code)) {
                $output->writeln('<error>非交互模式下必须提供有效的 code 参数（kebab-case）</error>');
                return 1;
            }
        }

        try {
            while ($code === '' || !preg_match(self::CODE_REGEX, $code)) {
                $code = (string) $output->ask($input, 'plugin code（kebab-case，如 demo-app）');
                if (!preg_match(self::CODE_REGEX, $code)) {
                    $output->writeln('<error>code 必须是 kebab-case（小写字母开头，2-80 字符）</error>');
                    $code = '';
                }
            }

            $name        = (string) $output->ask($input, '显示名称', $code);
            $version     = (string) $output->ask($input, '版本号', '1.0.0');
            $kind        = (string) $output->choice($input, '类型', ['plugin', 'app'], 'plugin');
            $author      = (string) ($output->ask($input, '作者', '') ?? '');
            $description = (string) ($output->ask($input, '描述', '') ?? '');

            $config = [
                'code' => $code, 'name' => $name, 'version' => $version, 'kind' => $kind,
                'author' => $author, 'description' => $description,
                'force' => (bool) $input->getOption('force'),
                'with_uniapp' => false,
            ];

            if ($kind === 'app') {
                $config['menu_name'] = (string) $output->ask($input, '顶级菜单名', $name);
                $config['menu_path'] = (string) $output->ask($input, '菜单 path', '/' . $code);
            }
            $config['with_uniapp'] = (bool) $output->confirm($input, '生成 uniapp 分包脚手架？', false);

            $files = (new PluginScaffolder())->generate($config);
        } catch (\Throwable $e) {
            $output->writeln('<error>生成失败：' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln('<info>已生成插件 ' . $code . '：</info>');
        foreach ($files as $f) {
            $output->writeln('  + ' . $f);
        }
        $output->writeln('打包：<comment>php think plugin:pack ' . $code . '</comment>');
        return 0;
    }
}
