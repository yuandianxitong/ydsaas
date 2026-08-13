<template>
    <div class="page-header">
        <div class="page-header__main">
            <h1 class="page-header__title">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="page-header__subtitle">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.default || $slots.actions" class="page-header__actions">
            <slot name="actions"><slot /></slot>
        </div>
    </div>
</template>

<script lang="ts" setup>
interface Props {
    /** 页面/区块标题（名词短语，4–6 字） */
    title?: string
    /** 副标题：范围 / 口径说明 */
    subtitle?: string
}

withDefaults(defineProps<Props>(), {
    title: '',
    subtitle: ''
})
</script>

<style scoped lang="scss">
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-4);

    &__title {
        margin: 0;
        font-size: var(--font-size-title);
        font-weight: var(--font-weight-semibold);
        line-height: var(--line-height-tight);
        letter-spacing: -0.01em;
        color: var(--color-text-primary);
    }

    &__subtitle {
        margin: 4px 0 0;
        font-size: var(--font-size-caption);
        color: var(--color-text-tertiary);
    }

    &__actions {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        flex: none;
    }
}
</style>
