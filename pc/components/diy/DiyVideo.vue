<template>
  <div class="diy-video">
    <video v-if="p.src" :src="p.src" :poster="p.poster || ''" :autoplay="!!p.autoplay" :loop="!!p.loop" :muted="!!p.autoplay" controls playsinline class="v" :style="{ height: `${(p.height || 200) / 2}px` }" />
    <div v-else class="empty" :style="{ height: `${(p.height || 200) / 2}px` }">视频</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
</script>
<style scoped>
.diy-video { width: 100%; }
.v { width: 100%; display: block; background: #000; }
.empty { display: flex; align-items: center; justify-content: center; background: #eef1f6; color: #9aa4b2; font-size: 12px; }
</style>
