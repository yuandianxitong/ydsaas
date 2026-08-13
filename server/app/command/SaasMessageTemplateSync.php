<?php

/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use app\service\saas\TenantInitService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * saas:message-template-sync
 *
 * 以 tenant_id=0 模板为准，为存量租户补插缺失的消息模板（按 code 判重，幂等只增不改）。
 * 解决：TenantInitService 历史版本不复制 message_templates，存量租户 trySend 一律
 * 「模板不存在」（#6）。补插逻辑复用 TenantInitService::syncMessageTemplates，
 * 与新租户初始化同一份代码。
 *
 * 用法：
 *   php think saas:message-template-sync              # 全部存量租户
 *   php think saas:message-template-sync --tenant=12  # 仅某租户
 *   php think saas:message-template-sync --dry-run    # 只报告将要补插（事务内执行后回滚）
 */
class SaasMessageTemplateSync extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:message-template-sync')
            ->setDescription('以模板(tenant_id=0)为准为存量租户补插缺失的消息模板（幂等只增不改）')
            ->addOption('tenant', null, Option::VALUE_OPTIONAL, '只处理某个 tenant_id', null)
            ->addOption('dry-run', null, Option::VALUE_NONE, '只报告将要补插，不写库（事务回滚）');
    }

    protected function execute(Input $input, Output $output): int
    {
        $only = $input->getOption('tenant');
        $dry  = (bool) $input->getOption('dry-run');

        $q = Db::table('tenants')->whereNull('deleted_at')->where('id', '>', 0);
        if ($only !== null) {
            $q->where('id', (int) $only);
        }
        $ids = $q->column('id');

        /** @var TenantInitService $svc */
        $svc = app(TenantInitService::class);

        $total   = 0;
        $changed = 0;
        $failed  = 0;
        foreach ($ids as $tid) {
            try {
                Db::startTrans();
                $inserted = $svc->syncMessageTemplates((int) $tid);
                $dry ? Db::rollback() : Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                $failed++;
                $output->writeln(sprintf('  tenant %d 失败: %s', $tid, $e->getMessage()));
                continue;
            }

            if ($inserted > 0) {
                $changed++;
                $output->writeln(sprintf('  tenant %d: +%d 模板', $tid, $inserted));
            }
            $total += $inserted;
        }

        $output->writeln(sprintf(
            'message-template-sync 完成：%d/%d 租户需补插，共 %d 条%s%s',
            $changed,
            count($ids),
            $total,
            $dry ? '（dry-run 未写库）' : '',
            $failed > 0 ? "，{$failed} 个租户失败" : ''
        ));

        return $failed > 0 ? 1 : 0;
    }
}
