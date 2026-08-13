<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\saga;

/**
 * v2.7.0：Saga 编排引擎。
 *
 * 顺序执行步骤；任一 step 抛异常时，按已成功完成的逆序触发补偿；最后把原始异常抛回去。
 *
 * 特性：
 * - 单 step 的补偿失败仅 error_log + 进 compensationErrors，不会阻断其它 step 的清理
 * - 不参与数据库事务 —— 跨多副作用域（DB、文件系统、消息队列），由各 step 自己决定内部事务
 * - 失败 step 本身不会被补偿（它的 execute 没成功，没有副作用可逆）
 */
class SagaRunner
{
    /** @var array<int, CompensatableStep> 已成功执行的步骤（按入栈顺序） */
    private array $executedSteps = [];

    /** @var array<int, array{step: string, error: string}> 补偿过程中失败的记录 */
    private array $compensationErrors = [];

    /**
     * @param CompensatableStep[] $steps
     * @throws \Throwable 原始异常透传
     */
    public function run(array $steps): void
    {
        $this->executedSteps      = [];
        $this->compensationErrors = [];

        foreach ($steps as $step) {
            try {
                $step->execute();
                $this->executedSteps[] = $step;
            } catch (\Throwable $e) {
                $this->compensateAll();
                throw $e;
            }
        }
    }

    /**
     * @return array<int, array{step: string, error: string}>
     */
    public function compensationErrors(): array
    {
        return $this->compensationErrors;
    }

    /**
     * @return CompensatableStep[]
     */
    public function executedSteps(): array
    {
        return $this->executedSteps;
    }

    private function compensateAll(): void
    {
        foreach (array_reverse($this->executedSteps) as $step) {
            try {
                $step->compensate();
            } catch (\Throwable $e) {
                $name = $step->name();
                $msg  = $e->getMessage();
                error_log("[saga] compensate '{$name}' failed: {$msg}");
                $this->compensationErrors[] = ['step' => $name, 'error' => $msg];
            }
        }
    }
}
