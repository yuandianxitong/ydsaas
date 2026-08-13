<template>
    <aside class="flex h-screen">
        <div v-show="!isMobile" class="contents">
            <platform-sidebar />
        </div>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import useAppStore from '@/store/modules/app.store'

import PlatformSidebar from './platform-sidebar.vue'

const appStore = useAppStore()
const isMobile = computed(() => appStore.isMobile)
</script>
