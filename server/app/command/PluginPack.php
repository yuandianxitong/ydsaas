<?php

/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use app\service\plugin\PluginPacker;
use core\exception\BusinessException;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

/**
 * plugin:pack
 *
 * 校验本地 plugins/<code>/ 并打包成 runtime/plugin-packages/<code>-<version>.zip。
 * 复用 PluginPackageInstaller::inspect，保证产出 zip 能通过上传/安装。
 *
 * 用法：php think plugin:pack <code> [--output=dir] [--force]
 */
class PluginPack extends Command
{
    private ?string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:pack')
            ->setDescription('校验并打包本地插件到 runtime/plugin-packages/')
            ->addArgument('code', Argument::REQUIRED, '插件 code')
            ->addOption('output', 'o', Option::VALUE_REQUIRED, '输出目录（默认 runtime/plugin-packages/）')
            ->addOption('force', 'f', Option::VALUE_NONE, '覆盖已存在的 zip');
    }

    protected function execute(Input $input, Output $output): int
    {
        $code = (string) $input->getArgument('code');
        $outputDir = $input->getOption('output');
        $force = (bool) $input->getOption('force');

        try {
            $path = (new PluginPacker($this->root))->pack($code, $outputDir !== null ? (string) $outputDir : null, $force);
        } catch (BusinessException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        } catch (\Throwable $e) {
            $output->writeln('<error>打包失败：' . $e->getMessage() . '</error>');
            return 1;
        }

        $size = is_file($path) ? round(filesize($path) / 1024, 1) : 0;
        $output->writeln('<info>已打包：</info> ' . $path . ' (' . $size . ' KB)');
        return 0;
    }
}
