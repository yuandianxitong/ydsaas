<template>
    <el-tag
        class="semantic-badge"
        :class="{ 'is-neutral': type === 'neutral' }"
        :type="tagType"
        :effect="effect"
        :size="size"
        :round="false"
        disable-transitions
    >
        <slot>{{ label }}</slot>
    </el-tag>
</template>

<script lang="ts" setup>
import { computed } from 'vue'

type BadgeType = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'neutral'

interface Props {
    /** 语义类型；neutral = 中性灰（映射到 EP info 并降饱和） */
    type?: BadgeType
    /** 文本（也可用默认插槽） */
    label?: string
    effect?: 'dark' | 'light' | 'plain'
    size?: '' | 'large' | 'default' | 'small'
}

const props = withDefaults(defineProps<Props>(), {
    type: 'primary',
    label: '',
    effect: 'light',
    size: 'small'
})

// neutral 没有对应 EP 类型，统一落到 info 再由样式降饱和
const tagType = computed(() => (props.type === 'neutral' ? 'info' : props.type))
</script>

<style scoped lang="scss">
// 原型 Badge：22px 胶囊、11px/600、sharp 圆角（r-xs = 2px）
.semantic-badge {
    height: 22px;
    padding: 0 8px;
    border: none;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-small);
    font-weight: var(--font-weight-semibold);
    line-height: 1;

    // neutral：中性灰软填充（原型 rgba(128,137,160,.12) + ink-3）
    &.is-neutral {
        color: var(--ink-3);
        background: rgba(128, 137, 160, 0.12);
    }
}
</style>
