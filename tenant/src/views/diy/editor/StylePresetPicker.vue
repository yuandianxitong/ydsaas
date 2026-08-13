<template>
    <div class="spp">
        <button
            v-for="o in options"
            :key="String(o.value)"
            type="button"
            class="spp__item"
            :class="{ 'spp__item--active': activeValue === o.value }"
            @click="onSelect(o)"
        >
            <div class="spp__thumb" :class="`spp__thumb--${o.value}`">
                <img v-if="o.thumb" :src="o.thumb" alt="" class="spp__img" />
                <div v-else class="spp__wire" :data-skin="o.value" aria-hidden="true" />
                <span v-if="!o.thumb" class="spp__mini">{{ o.label.slice(0, 2) }}</span>
            </div>
            <span class="spp__label">{{ o.label }}</span>
        </button>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { applyStylePreset, type StylePresetOption } from './stylePreset'

const props = withDefaults(
    defineProps<{
        modelValue: string
        options: StylePresetOption[]
        /** 写入 props 的键名，默认 style */
        styleKey?: string
        /** 组件完整 props（用于合并 patch） */
        targetProps?: Record<string, any> | null
        /** 缺省回退值（如 default_props.style） */
        fallback?: string
    }>(),
    { styleKey: 'style', targetProps: null, fallback: '' }
)

const activeValue = computed(() => {
    if (props.modelValue !== undefined && props.modelValue !== null && props.modelValue !== '') {
        return props.modelValue
    }
    if (props.fallback) return props.fallback
    return props.options[0]?.value ?? ''
})

const emit = defineEmits<{
    (e: 'update:modelValue', v: string): void
    (e: 'begin'): void
    (e: 'change', option: StylePresetOption): void
}>()

function onSelect(o: StylePresetOption) {
    emit('begin')
    if (props.targetProps) {
        applyStylePreset(props.targetProps, o, props.styleKey)
    }
    emit('update:modelValue', o.value)
    emit('change', o)
}
</script>

<style scoped lang="scss">
.spp {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    width: 100%;
}
.spp__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 6px;
    border: 1px solid var(--el-border-color-lighter, #ebeef5);
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.spp__item--active {
    border-color: var(--el-color-primary);
    box-shadow: 0 0 0 1px var(--el-color-primary);
}
.spp__thumb {
    width: 100%;
    aspect-ratio: 16 / 10;
    border-radius: 6px;
    background: #f5f7fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.spp__thumb--classic {
    background: #fffbe6;
}
.spp__thumb--minimal {
    background: #ffffff;
    border: 1px solid #eee;
}
.spp__thumb--dark {
    background: #1f2430;
    color: #fff;
}
.spp__thumb--grid-portrait,
.spp__thumb--card-premium,
.spp__thumb--feed-cover,
.spp__thumb--tile-dial {
    background: linear-gradient(160deg, #f7f8fa 40%, #e8ecf2 40%);
}
.spp__thumb--grid-square,
.spp__thumb--list-media,
.spp__thumb--card-grid,
.spp__thumb--list-action {
    background: linear-gradient(90deg, #eceff3 35%, #f7f8fa 35%);
}
.spp__thumb--list-row,
.spp__thumb--scroll-store,
.spp__thumb--timeline,
.spp__thumb--hotline-hero {
    background: linear-gradient(180deg, #dde3ea 30%, #f7f8fa 30%);
}
.spp__thumb--scroll-card {
    background: linear-gradient(110deg, #f0f2f5 50%, #fff 50%);
}
/* 图片魔方线框缩略 */
.spp__thumb--row-2 {
    background: linear-gradient(90deg, #cfd6e0 49%, transparent 49%, transparent 51%, #cfd6e0 51%);
}
.spp__thumb--row-3 {
    background: repeating-linear-gradient(90deg, #cfd6e0 0, #cfd6e0 30%, transparent 30%, transparent 35%);
}
.spp__thumb--row-4 {
    background: repeating-linear-gradient(90deg, #cfd6e0 0, #cfd6e0 22%, transparent 22%, transparent 25%);
}
.spp__thumb--grid-2x2 {
    background:
        linear-gradient(#cfd6e0, #cfd6e0) 0 0 / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 0 / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 0 100% / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 100% / 48% 48% no-repeat;
}
.spp__thumb--left1-right2 {
    background:
        linear-gradient(#b8c0cc, #b8c0cc) 0 0 / 48% 100% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 0 / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 100% / 48% 48% no-repeat;
}
.spp__thumb--left2-right1 {
    background:
        linear-gradient(#cfd6e0, #cfd6e0) 0 0 / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 0 100% / 48% 48% no-repeat,
        linear-gradient(#b8c0cc, #b8c0cc) 100% 0 / 48% 100% no-repeat;
}
.spp__thumb--top1-bottom2 {
    background:
        linear-gradient(#b8c0cc, #b8c0cc) 0 0 / 100% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 0 100% / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 100% / 48% 48% no-repeat;
}
.spp__thumb--top2-bottom1 {
    background:
        linear-gradient(#cfd6e0, #cfd6e0) 0 0 / 48% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 0 / 48% 48% no-repeat,
        linear-gradient(#b8c0cc, #b8c0cc) 0 100% / 100% 48% no-repeat;
}
.spp__thumb--left1-right3 {
    background:
        linear-gradient(#b8c0cc, #b8c0cc) 0 0 / 48% 100% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 0 / 48% 30% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 50% / 48% 30% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 100% / 48% 30% no-repeat;
}
.spp__thumb--top1-bottom3 {
    background:
        linear-gradient(#b8c0cc, #b8c0cc) 0 0 / 100% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 0 100% / 30% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 50% 100% / 30% 48% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 100% / 30% 48% no-repeat;
}
.spp__thumb--l-shape {
    background:
        linear-gradient(#b8c0cc, #b8c0cc) 0 0 / 48% 64% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 0 / 48% 30% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 35% / 48% 30% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 0 100% / 100% 30% no-repeat;
}
/* 轮播搜索 */
.spp__thumb--card {
    background:
        linear-gradient(#e8ecf2, #e8ecf2) center / 72% 70% no-repeat;
    box-shadow: inset 0 0 0 1px #f5f7fa;
}
.spp__thumb--peek {
    background:
        linear-gradient(#cfd6e0, #cfd6e0) 0 50% / 14% 70% no-repeat,
        linear-gradient(#b8c0cc, #b8c0cc) 50% 50% / 58% 78% no-repeat,
        linear-gradient(#cfd6e0, #cfd6e0) 100% 50% / 14% 70% no-repeat;
}
.spp__thumb--card .spp__mini,
.spp__thumb--peek .spp__mini {
    display: none;
}
.spp__thumb--row-2 .spp__mini,
.spp__thumb--row-3 .spp__mini,
.spp__thumb--row-4 .spp__mini,
.spp__thumb--grid-2x2 .spp__mini,
.spp__thumb--left1-right2 .spp__mini,
.spp__thumb--left2-right1 .spp__mini,
.spp__thumb--top1-bottom2 .spp__mini,
.spp__thumb--top2-bottom1 .spp__mini,
.spp__thumb--left1-right3 .spp__mini,
.spp__thumb--top1-bottom3 .spp__mini,
.spp__thumb--l-shape .spp__mini {
    display: none;
}
.spp__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.spp__mini {
    font-size: 11px;
    color: #909399;
    font-weight: 600;
}
.spp__thumb--dark .spp__mini {
    color: #ddd;
}
.spp__label {
    font-size: 11px;
    color: #606266;
    line-height: 1.2;
    text-align: center;
}
.spp__item--active .spp__label {
    color: var(--el-color-primary);
    font-weight: 600;
}
</style>
