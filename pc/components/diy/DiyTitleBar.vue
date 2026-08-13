<template>
  <div class="diy-title-bar" :style="{ justifyContent: p.align === 'center' ? 'center' : 'flex-start' }">
    <span class="t">{{ p.title }}</span>
    <span v-if="p.subtitle" class="s">{{ p.subtitle }}</span>
    <a v-if="safeLink(p.more_link)" :href="safeLink(p.more_link)" rel="noopener noreferrer" class="m">{{ p.more_text || '更多' }}</a>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
</script>
<style scoped>
.diy-title-bar { padding: 10px; display: flex; align-items: baseline; }
.t { font-size: 16px; font-weight: 600; color: #222; }
.s { font-size: 12px; color: #999; margin-left: 6px; }
.m { font-size: 12px; color: #999; margin-left: auto; text-decoration: none; }
</style>
