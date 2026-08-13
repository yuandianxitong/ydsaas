<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\diy\contracts;

/**
 * 插件贡献的数据驱动 DIY widget 注水契约。
 * 由插件实现（PSR-4 Plugin\ 下），下发已发布树时按 widget type 调用。
 */
interface DiyWidgetHydrator
{
    /**
     * 为某 widget 注入实时数据，返回新的 props。
     * 抛异常 → 下发路径丢弃该组件（fail-safe，绝不让单个 widget 拖垮整页）。
     * 应为只读操作，不产生副作用。
     *
     * @param array<string,mixed> $props 已发布原始 props（含 componentStyle 等）
     * @param int $tenantId 目标租户
     * @return array<string,mixed> 注水后的 props
     */
    public function hydrate(array $props, int $tenantId): array;
}
