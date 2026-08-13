<template>
    <span class="status-dot">
        <span class="status-dot__dot" :class="{ 'has-halo': halo }" :style="dotStyle" />
        <span v-if="label || $slots.default" class="status-dot__label">
            <slot>{{ label }}</slot>
        </span>
    </span>
</template>

<script lang="ts" setup>
import { computed } from 'vue'

type DotType = 'success' | 'warning' | 'danger' | 'info' | 'primary' | 'neutral'

interface Props {
    /** 语义类型，决定圆点颜色 */
    type?: DotType
    /** 行内标签文本（也可用默认插槽） */
    label?: string
    /** 是否显示同色光晕（活跃/同步中等动态状态） */
    halo?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    type: 'success',
    label: '',
    halo: false
})

const COLOR_MAP: Record<DotType, string> = {
    success: 'var(--color-success)',
    warning: 'var(--color-warning)',
    danger: 'var(--color-danger)',
    info: 'var(--color-info)',
    primary: 'var(--color-brand)',
    neutral: 'var(--ink-3)'
}

const dotStyle = computed(() => {
    const c = COLOR_MAP[props.type]
    return {
        background: c,
        boxShadow: props.halo ? `0 0 0 3px color-mix(in srgb, ${c} 18%, transparent)` : 'none'
    }
})
</script>

<style scoped lang="scss">
.status-dot {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    &__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex: none;
    }

    &__label {
        font-size: var(--font-size-caption);
        color: var(--color-text-secondary);
    }
}
</style>
