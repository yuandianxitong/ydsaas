<?php

declare(strict_types=1);

namespace app\command;

use app\service\marketplace\LicenseStateMachine;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 周期性评估 marketplace 插件的 license_status 状态机
 * cron 建议: 0 *\/6 * * *  （每 6 小时）
 */
class SaasLicenseEvaluate extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:license-evaluate')
            ->setDescription('评估 marketplace 插件 license_status 状态机 (active/grace/expired)');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            app(LicenseStateMachine::class)->evaluateAll();
            $output->info('license-evaluate done');
            return 0;
        } catch (\Throwable $e) {
            $output->error('license-evaluate failed: ' . $e->getMessage());
            return 1;
        }
    }
}
