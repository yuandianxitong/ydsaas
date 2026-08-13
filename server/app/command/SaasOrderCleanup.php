<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\model\saas\SaasOrder;

/**
 * saas:order-cleanup
 *
 * 扫描过期未支付的 SaaS 订单，批量取消（status 1 → 3）。
 *
 * - 只处理 status = 1 (待支付) 且 expired_at < now 的订单
 * - 不触发 subscription 副作用（纯清理，跟 markCancelled 等价但跳过 Service 层
 *   事务，batch 场景更高效）
 * - 空库 / 无过期订单时优雅退出
 *
 * 推荐部署：每 5 分钟 cron
 *   *\/5 * * * * cd /path/to/server && php think saas:order-cleanup
 */
class SaasOrderCleanup extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:order-cleanup')
            ->setDescription('Cancel expired pending SaaS orders');
    }

    protected function execute(Input $input, Output $output): int
    {
        // 过期后再给 60 秒缓冲，留给支付回调时间窗口抵达
        $cutoff = date('Y-m-d H:i:s', time() - 60);
        $now = date('Y-m-d H:i:s');

        // 原子条件 UPDATE：只影响 status 仍为 1 的行。
        // 如果同一时刻回调先把订单 mark 为 2，这里的 where 不会命中。
        $cancelled = SaasOrder::where('status', 1)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', $cutoff)
            ->limit(500)
            ->update(['status' => 3]);

        $output->writeln(sprintf(
            '[%s] Expired-orders cleanup: cancelled %d orders (cutoff=%s)',
            $now,
            (int) $cancelled,
            $cutoff
        ));
        return 0;
    }
}
