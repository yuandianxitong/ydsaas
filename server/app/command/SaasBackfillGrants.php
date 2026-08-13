<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 一次性数据迁移：把 plans.features JSON 数组里的每个 code 转换为 plugin_grants 行。
 *
 * 用法：
 *   php think saas:backfill-grants            # 等价 --dry-run，只报告
 *   php think saas:backfill-grants --dry-run
 *   php think saas:backfill-grants --apply
 *
 * --apply 时：
 *   - 命中（plans.features[i] 对得上 plugins.code）→ INSERT IGNORE plugin_grants(... auto_enable=1)。
 *   - 任何 unmatched → 不写库，exit 1，stderr 输出 (plan_id, plan_name, feature_code) 明细。
 */
class SaasBackfillGrants extends Command
{
    protected function configure(): void
    {
        $this->setName('saas:backfill-grants')
            ->setDescription('迁移 plans.features → plugin_grants（一次性）')
            ->addOption('apply', null, Option::VALUE_NONE, '实际写入数据库；缺省为 dry-run')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只报告，不写库');
    }

    protected function execute(Input $input, Output $output): int
    {
        $apply = (bool) $input->getOption('apply');

        $plans = Db::name('plans')
            ->whereNotNull('features')
            ->field('id, name, features')
            ->select()->toArray();

        $pluginByCode = [];
        foreach (Db::name('plugins')->field('id, code')->select() as $p) {
            $pluginByCode[(string) $p['code']] = (int) $p['id'];
        }

        $matchedRows = [];
        $unmatched = [];

        foreach ($plans as $plan) {
            $features = json_decode((string) $plan['features'], true) ?? [];
            if (!is_array($features)) {
                $features = [];
            }
            foreach ($features as $featureCode) {
                $featureCode = (string) $featureCode;
                if ($featureCode === '') {
                    continue;
                }
                if (isset($pluginByCode[$featureCode])) {
                    $matchedRows[] = [
                        'plan_id'     => (int) $plan['id'],
                        'plugin_id'   => $pluginByCode[$featureCode],
                        'plugin_code' => $featureCode,
                    ];
                } else {
                    $unmatched[] = [
                        'plan_id'      => (int) $plan['id'],
                        'plan_name'    => (string) $plan['name'],
                        'feature_code' => $featureCode,
                    ];
                }
            }
        }

        $output->writeln(sprintf(
            'Plans inspected: %d; matched features: %d; unmatched: %d',
            count($plans),
            count($matchedRows),
            count($unmatched)
        ));

        if (!empty($unmatched)) {
            $output->writeln('<error>Unmatched feature codes:</error>');
            foreach ($unmatched as $u) {
                $output->writeln(sprintf('  plan_id=%d "%s" → "%s"', $u['plan_id'], $u['plan_name'], $u['feature_code']));
            }
        }

        if (!$apply) {
            $output->writeln('<info>dry-run; nothing written.</info>');
            return empty($unmatched) ? 0 : 1;
        }

        if (!empty($unmatched)) {
            $output->writeln('<error>Refusing to apply: unmatched codes above must be resolved first.</error>');
            return 1;
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            foreach ($matchedRows as $row) {
                // INSERT IGNORE for idempotency (re-run safe)
                Db::query(
                    'INSERT IGNORE INTO plugin_grants (plan_id, plugin_id, plugin_code, auto_enable, created_at) VALUES (?, ?, ?, 1, ?)',
                    [$row['plan_id'], $row['plugin_id'], $row['plugin_code'], $now]
                );
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }

        $output->writeln(sprintf('<info>Inserted %d grant rows (some may be idempotent ignores).</info>', count($matchedRows)));
        return 0;
    }
}
