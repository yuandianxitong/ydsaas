<?php

declare(strict_types=1);

namespace app\command;

use app\model\marketplace\MarketplaceConnection;
use app\repository\marketplace\MarketplaceConnectionRepository;
use app\service\marketplace\MarketplaceCatalogService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class SaasMarketplaceSync extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:marketplace-sync')
            ->setDescription('同步官方市场已购应用清单 + 心跳')
            ->addOption('connection-id', null, Option::VALUE_OPTIONAL, '只处理某连接', null);
    }

    protected function execute(Input $input, Output $output): int
    {
        $repo    = app(MarketplaceConnectionRepository::class);
        $catalog = app(MarketplaceCatalogService::class);

        $idOpt = $input->getOption('connection-id');
        $query = (new MarketplaceConnection())
            ->where('status', 'active')
            ->whereNull('deleted_at');
        if ($idOpt !== null) {
            $query = $query->where('id', (int) $idOpt);
        }
        $rows = $query->select()->toArray();

        $totalSynced = 0;
        foreach ($rows as $conn) {
            try {
                $r = $catalog->syncConnection($conn);
                $output->writeln(sprintf('[connection #%d] synced=%d removed=%d',
                    $conn['id'], $r['changed_count'], $r['removed_count']));
                $totalSynced++;
            } catch (\Throwable $e) {
                $output->writeln(sprintf('[connection #%d] FAIL: %s',
                    $conn['id'], $e->getMessage()));
                $repo->markError((int) $conn['id'], $e->getMessage());
            }
        }
        $output->writeln(sprintf('Done. %d/%d connections synced.', $totalSynced, count($rows)));
        return 0;
    }
}
