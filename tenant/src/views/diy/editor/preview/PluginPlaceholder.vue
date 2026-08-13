<template>
    <div class="plugin-ph">
        <div class="plugin-ph__label">
            {{ meta?.label || '插件组件'
            }}<span class="plugin-ph__hint">{{ hint || '插件组件 · 发布后端上加载真实数据' }}</span>
        </div>

        <!-- tab-goods: tab 条 + 双列格 -->
        <template v-if="skeleton === 'tab-goods'">
            <div class="ph-tabs">
                <span
                    v-for="(t, i) in tabLabels"
                    :key="i"
                    class="ph-tab"
                    :class="{ 'ph-tab--on': i === 0 }"
                    >{{ t }}</span
                >
            </div>
            <div class="ph-grid ph-grid--2"><div v-for="i in 4" :key="i" class="ph-cell" /></div>
        </template>

        <!-- 横滑条 -->
        <div v-else-if="skeleton === 'scroll-x'" class="ph-scroll">
            <div v-for="i in 4" :key="i" class="ph-cell ph-cell--fixed" />
        </div>

        <!-- 三列格 / 双列格 -->
        <div v-else-if="skeleton === 'grid-3'" class="ph-grid ph-grid--3">
            <div v-for="i in 6" :key="i" class="ph-cell" />
        </div>
        <div v-else-if="skeleton === 'card-list'" class="ph-grid ph-grid--2">
            <div v-for="i in 4" :key="i" class="ph-cell" />
        </div>

        <!-- 列表行 -->
        <div v-else-if="skeleton === 'list'" class="ph-rows">
            <div v-for="i in 3" :key="i" class="ph-rowline" />
        </div>

        <!-- 券组 -->
        <div v-else-if="skeleton === 'coupon-row'" class="ph-coupon-row">
            <div v-for="i in 3" :key="i" class="ph-coupon-card">
                <div class="ph-coupon-left" />
                <div class="ph-coupon-right">
                    <div class="ph-coupon-line" />
                    <div class="ph-coupon-line" />
                </div>
            </div>
        </div>

        <!-- 秒杀组 -->
        <div v-else-if="skeleton === 'seckill-row'" class="ph-seckill-row">
            <div class="ph-seckill-header">
                <div class="ph-seckill-header-left" />
                <div class="ph-seckill-header-right" />
            </div>
            <div class="ph-seckill-cards">
                <div v-for="i in 3" :key="i" class="ph-seckill-card">
                    <div class="ph-seckill-image" />
                    <div class="ph-seckill-line" />
                    <div class="ph-seckill-line" />
                    <div class="ph-seckill-progress" />
                </div>
            </div>
        </div>

        <!-- 入口宫格（member 专属） -->
        <div v-else-if="skeleton === 'entry-grid'" class="ph-entries">
            <div v-for="i in 4" :key="i" class="ph-entry">
                <div class="ph-entry-icon" />
                <div class="ph-entry-line" />
            </div>
        </div>

        <!-- 资产格（member 专属） -->
        <div v-else-if="skeleton === 'asset-card'" class="ph-assets">
            <div v-for="i in 3" :key="i" class="ph-entry">
                <div class="ph-entry-line ph-entry-line--num" />
                <div class="ph-entry-line" />
            </div>
        </div>

        <!-- 未知 render：沿用原通用占位 -->
        <div v-else-if="meta && meta.props_schema.length" class="plugin-ph__cfg">
            <div v-for="f in meta.props_schema" :key="f.key" class="plugin-ph__row">
                <span class="plugin-ph__k">{{ f.label }}</span>
                <span class="plugin-ph__v">{{ props?.[f.key] }}</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import type { PluginWidgetMeta } from '@/api/diy'

import { useApiOptions } from '../useApiOptions'

const p = defineProps<{ props: Record<string, any>; meta: PluginWidgetMeta | null; hint?: string }>()

// goods-grid 声明 render 是缺省 card-list，但实际形态由 layout 决定 —— 骨架按 props.layout 优先
const LAYOUT_TO_RENDER: Record<string, string> = {
    'grid-2': 'card-list',
    'grid-3': 'grid-3',
    list: 'list',
    scroll: 'scroll-x'
}
const skeleton = computed(() => {
    const layout = String(p.props?.layout || '')
    if (LAYOUT_TO_RENDER[layout]) return LAYOUT_TO_RENDER[layout]
    const r = p.meta?.render || ''
    return ['card-list', 'grid-3', 'scroll-x', 'tab-goods', 'list', 'coupon-row', 'seckill-row', 'entry-grid', 'asset-card'].includes(r) ? r : ''
})

// tab-goods：经 schema 内 api-multi-select 字段的 options_url 解析已选 id → 名称（零插件耦合）
const tabLabels = computed<string[]>(() => {
    const field = p.meta?.props_schema.find((f) => f.type === 'api-multi-select' && f.options_url)
    const ids: any[] = Array.isArray(p.props?.[field?.key || '']) ? p.props[field!.key] : []
    if (!field || !ids.length) return ['Tab 1', 'Tab 2']
    const options = useApiOptions(field.options_url!)
    return ids.map((id, i) => options.value.find((o) => o.value === id)?.label || `Tab ${i + 1}`)
})
</script>

<style scoped>
.plugin-ph {
    border: 1px dashed #c0c4cc;
    border-radius: 6px;
    padding: 12px;
    margin: 8px;
    background: #fafafa;
}
.plugin-ph__label {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}
.plugin-ph__hint {
    font-size: 11px;
    color: #9aa4b2;
    font-weight: 400;
    margin-left: 8px;
}
.ph-tabs {
    display: flex;
    gap: 12px;
    margin-bottom: 8px;
}
.ph-tab {
    font-size: 12px;
    color: #9aa4b2;
    padding-bottom: 4px;
}
.ph-tab--on {
    color: #333;
    border-bottom: 2px solid #909399;
}
.ph-grid {
    display: grid;
    gap: 6px;
}
.ph-grid--2 {
    grid-template-columns: repeat(2, 1fr);
}
.ph-grid--3 {
    grid-template-columns: repeat(3, 1fr);
}
.ph-cell {
    height: 72px;
    border-radius: 4px;
    background: #ebeef5;
}
.ph-scroll {
    display: flex;
    gap: 6px;
    overflow: hidden;
}
.ph-cell--fixed {
    width: 88px;
    flex-shrink: 0;
    height: 72px;
}
.ph-rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.ph-rowline {
    height: 40px;
    border-radius: 4px;
    background: #ebeef5;
}
.plugin-ph__cfg {
    border-top: 1px solid #eee;
    padding-top: 8px;
}
.plugin-ph__row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #666;
    padding: 2px 0;
}
.plugin-ph__k {
    color: #999;
}
.ph-coupon-row {
    display: flex;
    gap: 12px;
    overflow: hidden;
}
.ph-coupon-card {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 4px;
    padding: 8px;
    width: 140px;
}
.ph-coupon-left {
    width: 28px;
    height: 56px;
    background: #ebeef5;
    border-radius: 2px;
    flex-shrink: 0;
}
.ph-coupon-right {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}
.ph-coupon-line {
    height: 12px;
    background: #ebeef5;
    border-radius: 2px;
}
.ph-seckill-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ph-seckill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 4px;
    margin-bottom: 4px;
}
.ph-seckill-header-left {
    width: 60px;
    height: 16px;
    background: #ebeef5;
    border-radius: 2px;
}
.ph-seckill-header-right {
    width: 60px;
    height: 20px;
    background: #ebeef5;
    border-radius: 10px;
}
.ph-seckill-cards {
    display: flex;
    gap: 6px;
    overflow: hidden;
}
.ph-seckill-card {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 80px;
    flex-shrink: 0;
}
.ph-seckill-image {
    width: 80px;
    height: 80px;
    background: #ebeef5;
    border-radius: 4px;
}
.ph-seckill-line {
    height: 12px;
    background: #ebeef5;
    border-radius: 2px;
}
.ph-seckill-progress {
    height: 4px;
    background: #e4e7eb;
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}
.ph-entries,
.ph-assets {
    display: flex;
    gap: 6px;
}
.ph-entry {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 8px 0;
}
.ph-entry-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #ebeef5;
}
.ph-entry-line {
    width: 60%;
    height: 10px;
    border-radius: 2px;
    background: #ebeef5;
}
.ph-entry-line--num {
    width: 40%;
    height: 16px;
}
.ph-seckill-progress::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 40%;
    background: #909399;
    border-radius: 2px;
}
</style>
