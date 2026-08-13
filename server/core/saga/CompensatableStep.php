<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\saga;

/**
 * v2.7.0：可补偿步骤。
 *
 * Saga 中的每一步实现一对正反操作：
 * - execute()    正向：可以抛异常，框架会触发补偿
 * - compensate() 反向：仅在 execute() 已经成功完成后才被 SagaRunner 调用
 *
 * 实现要点：
 * - compensate() 必须幂等：可能因为补偿链上别的 step 抛错被重复触发
 * - compensate() 应尽力而为：内部抛错由 SagaRunner 捕获记日志后继续其它 step 的补偿，
 *   不要让一个 step 的清理失败阻断其它清理
 */
interface CompensatableStep
{
    /** 步骤名（仅用于日志/审计） */
    public function name(): string;

    public function execute(): void;

    /** 仅在 execute() 抛过的步骤不会调；execute() 成功后失败链 → 倒序调到这里 */
    public function compensate(): void;
}
