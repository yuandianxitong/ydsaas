<?php

declare(strict_types=1);

namespace app\command;

use app\service\marketplace\MarketplaceTokenRotationService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * marketplace_connections token 自动轮换
 * cron 建议: 0 3 * * *   （每天凌晨 3 点）
 */
class SaasMarketplaceTokenRotate extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:marketplace-token-rotate')
            ->setDescription('marketplace 连接 token 90d 自动轮换')
            ->addOption('connection-id', null, Option::VALUE_OPTIONAL, '只处理某连接', null);
    }

    protected function execute(Input $input, Output $output): int
    {
        $svc = app(MarketplaceTokenRotationService::class);
        $id  = $input->getOption('connection-id');
        if ($id !== null) {
            $ok = $svc->rotateOne((int) $id);
            $output->writeln($ok ? "rotated #{$id}" : "failed #{$id} (see last_error)");
            return $ok ? 0 : 1;
        }
        $svc->rotateAll();
        $output->info('token-rotate done');
        return 0;
    }
}
