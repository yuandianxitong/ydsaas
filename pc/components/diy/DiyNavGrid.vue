<template>
  <div class="diy-nav-grid">
    <template v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="item" :style="{ width: itemWidth }">
        <img :src="it.icon" class="icon" /><span class="text">{{ it.text }}</span>
      </a>
      <div v-else class="item" :style="{ width: itemWidth }">
        <img :src="it.icon" class="icon" /><span class="text">{{ it.text }}</span>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const cols = computed(() => props.props?.columns || 4)
const itemWidth = computed(() => `${100 / (cols.value || 4)}%`)
</script>
<style scoped>
.diy-nav-grid { display: flex; flex-wrap: wrap; padding: 10px; box-sizing: border-box; }
.item { display: flex; flex-direction: column; align-items: center; padding: 6px 0; box-sizing: border-box; text-decoration: none; }
.icon { width: 40px; height: 40px; object-fit: contain; }
.text { font-size: 12px; color: #333; margin-top: 4px; }
</style>
