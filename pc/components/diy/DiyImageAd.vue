<template>
  <div class="diy-image-ad">
    <template v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="item" :style="itemStyle">
        <img :src="it.image" class="img" />
      </a>
      <div v-else class="item" :style="itemStyle">
        <img :src="it.image" class="img" />
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const layout = computed(() => props.props?.layout || 'single')
const cols = computed(() => props.props?.columns || 2)
const itemStyle = computed(() => (layout.value === 'grid' ? { width: `${100 / (cols.value || 2)}%` } : { width: '100%' }))
</script>
<style scoped>
.diy-image-ad { display: flex; flex-wrap: wrap; }
.item { box-sizing: border-box; display: block; }
.img { width: 100%; height: auto; display: block; } /* widthFix */
</style>
