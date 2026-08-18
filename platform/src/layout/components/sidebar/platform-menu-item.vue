<template>
    <div
        class="nav-item flex flex-col items-center justify-center gap-2 py-4.5 px-1.5 rounded-10px cursor-pointer relative"
        :class="{ active }"
        @click="$emit('click')"
    >
        <i :class="[icon, 'nav-ic']" />
        <span class="nav-label">{{ title }}</span>
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
.nav-item {
    color: rgba(255, 255, 255, 0.45);
    transition: all 0.18s ease;

    &:hover {
        background: rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.8);
    }

    &.active {
        background: linear-gradient(135deg, var(--brand-500), var(--brand-600));
        box-shadow: 0 6px 18px var(--brand-shadow);
        color: #fff;
    }
}

.nav-ic {
    width: 22px;
    height: 22px;
    margin: 0;
    display: block;
    flex-shrink: 0;
}

.nav-label {
    font-size: 12px;
    line-height: 1;
    letter-spacing: 0.2px;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    color: inherit;
}
</style>
