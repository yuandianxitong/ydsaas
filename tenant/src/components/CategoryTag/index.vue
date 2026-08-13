<template>
    <el-tag class="category-tag" :style="tagStyle" :size="size" disable-transitions>
        <slot>{{ label }}</slot>
    </el-tag>
</template>

<script lang="ts" setup>
import { computed } from 'vue'

type AccentName =
    | 'indigo'
    | 'violet'
    | 'sky'
    | 'cyan'
    | 'green'
    | 'amber'
    | 'rose'
    | 'slate'
    | 'brand'

interface Props {
    /** 类目色：内置 accent 名或任意 hex（用于图表/类目色编码） */
    color?: AccentName | string
    label?: string
    size?: '' | 'large' | 'default' | 'small'
}

const props = withDefaults(defineProps<Props>(), {
    color: 'indigo',
    label: '',
    size: 'small'
})

// 原型 accent 调色板（charts / 类目色编码）
const ACCENTS: Record<AccentName, string> = {
    indigo: '#3B5BDB',
    violet: '#7C3AED',
    sky: '#0EA5E9',
    cyan: '#0891B2',
    green: '#16A34A',
    amber: '#F59E0B',
    rose: '#E11D48',
    slate: '#475569',
    brand: '#2C73FF'
}

const resolved = computed(() => ACCENTS[props.color as AccentName] ?? props.color)

// 原型 Tag：本色文字 + 14% alpha 软底，通过 EP tag 变量着色（仍是 el-tag）
const tagStyle = computed(() => ({
    '--el-tag-text-color': resolved.value,
    '--el-tag-bg-color': `color-mix(in srgb, ${resolved.value} 14%, transparent)`,
    '--el-tag-border-color': 'transparent'
}))
</script>

<style scoped lang="scss">
.category-tag {
    height: auto;
    padding: 2px 8px;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-small);
    font-weight: var(--font-weight-medium);
    line-height: 1.4;
}
</style>
