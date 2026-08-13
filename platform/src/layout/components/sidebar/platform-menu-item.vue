<template>
    <div class="platform-menu-item" :class="{ active: active }" @click="$emit('click')">
        <Icon :name="icon" :size="22" />
        <span class="label">{{ title }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { translateRouteTitle } from '@/utils/i18n'

interface Props {
    route: any
    active?: boolean
}

const props = defineProps<Props>()
defineEmits<{ click: [] }>()

const icon = computed(() => props.route.meta?.icon || '')
const title = computed(() =>
    translateRouteTitle(props.route.meta?.title as string, props.route.name)
)
</script>

<style lang="scss" scoped>
.platform-menu-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 80px;
    padding: 15px 0;
    cursor: pointer;
    transition:
        background 0.2s ease,
        color 0.2s ease;
    color: rgba(255, 255, 255, 0.6);

    .label {
        font-size: 11px;
        margin-top: 8px;
        transition: color 0.2s;
        text-align: center;
        line-height: 1.2;
        max-width: 70px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
    }

    &.active {
        background: rgba(var(--el-color-primary-rgb), 0.3);
        color: #fff;

        .label {
            color: #fff;
        }
    }
}
</style>
