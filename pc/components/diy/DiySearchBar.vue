<template>
  <div class="diy-search">
    <a v-if="safeLink(p.link)" :href="safeLink(p.link)" rel="noopener noreferrer" class="box" :style="boxStyle"><span class="ph">{{ p.placeholder || '搜索' }}</span></a>
    <div v-else class="box" :style="boxStyle"><span class="ph">{{ p.placeholder || '搜索' }}</span></div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
const boxStyle = computed(() => ({ borderRadius: `${(p.value.radius ?? 20) / 2}px`, backgroundColor: p.value.bg_color || '#f5f5f5' }))
</script>
<style scoped>
.diy-search { padding: 8px 10px; }
.box { height: 32px; display: flex; align-items: center; padding: 0 12px; text-decoration: none; }
.ph { color: #999; font-size: 13px; }
</style>
