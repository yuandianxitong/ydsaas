<template>
    <el-card class="kpi-card" shadow="never" :body-style="{ padding: '0' }">
        <div class="kpi-card__body">
            <div class="kpi-card__top">
                <span class="kpi-card__label">{{ label }}</span>
                <span
                    v-if="$slots.icon || icon"
                    class="kpi-card__icon"
                    :style="iconStyle"
                >
                    <slot name="icon" />
                </span>
            </div>

            <div class="kpi-card__value-row">
                <span class="kpi-card__value num">{{ value }}</span>
                <span v-if="unit" class="kpi-card__unit">{{ unit }}</span>
            </div>

            <div class="kpi-card__foot">
                <span
                    v-if="hasDelta"
                    class="kpi-card__delta num"
                    :class="deltaUp ? 'is-up' : 'is-down'"
                >
                    {{ deltaUp ? '↑' : '↓' }} {{ deltaText }}
                </span>
                <span v-if="caption" class="kpi-card__caption">{{ caption }}</span>
                <slot name="extra" />
            </div>
        </div>
    </el-card>
</template>

<script lang="ts" setup>
import { computed } from 'vue'

interface Props {
    /** 指标名称 */
    label: string
    /** 主数值（自动等宽对齐） */
    value: string | number
    /** 单位（如 个 / 元 / %） */
    unit?: string
    /** 环比/同比变化量；正数为 ↑（绿），负数为 ↓（红） */
    delta?: number | null
    /** delta 后缀，默认 % */
    deltaSuffix?: string
    /** 底部说明文案（如「本月 Top 6」「占活跃租户比」） */
    caption?: string
    /** 图标底色（accent 色），默认主色 */
    icon?: boolean
    accent?: string
}

const props = withDefaults(defineProps<Props>(), {
    unit: '',
    delta: null,
    deltaSuffix: '%',
    caption: '',
    icon: false,
    accent: 'var(--color-brand)'
})

const hasDelta = computed(() => props.delta !== null && props.delta !== undefined)
const deltaUp = computed(() => (props.delta ?? 0) >= 0)
const deltaText = computed(() => `${Math.abs(props.delta ?? 0)}${props.deltaSuffix}`)

// 图标 chip：accent 色 + 14% alpha 软底（原型规格）
const iconStyle = computed(() => ({
    color: props.accent,
    background: `color-mix(in srgb, ${props.accent} 14%, transparent)`
}))
</script>

<style scoped lang="scss">
.kpi-card {
    border: none;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-hairline);

    &__body {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        min-height: 132px;
        padding: 18px 20px 16px;
    }

    &__top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
    }

    &__label {
        font-size: var(--font-size-caption);
        color: var(--color-text-tertiary);
        letter-spacing: 0.02em;
    }

    &__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: var(--radius-lg);
    }

    &__value-row {
        display: flex;
        align-items: baseline;
        gap: var(--space-1);
        margin-top: auto;
    }

    &__value {
        font-size: var(--font-size-display);
        font-weight: var(--font-weight-semibold);
        line-height: var(--line-height-tight);
        letter-spacing: -0.02em;
        color: var(--color-text-primary);
    }

    &__unit {
        font-size: var(--font-size-body);
        color: var(--color-text-tertiary);
    }

    &__foot {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-caption);
        color: var(--color-text-tertiary);
    }

    &__delta {
        font-weight: var(--font-weight-semibold);

        &.is-up {
            color: var(--color-success);
        }

        &.is-down {
            color: var(--color-danger);
        }
    }

    &__caption {
        color: var(--color-text-tertiary);
    }
}
</style>
