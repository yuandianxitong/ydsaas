<template>
  <div class="pv-notice" :class="`pv-notice--${styleName}`">
    <template v-if="styleName === 'news'">
      <div class="pv-notice__head">
        <img v-if="props.props?.icon" :src="props.props.icon" class="pv-notice__icon" alt="" />
        <span class="pv-notice__tag">公告</span>
      </div>
      <div v-for="(it, i) in newsItems" :key="i" class="pv-notice__news-row">
        <span class="pv-notice__bullet">·</span>
        <span class="pv-notice__news-text">{{ it.text || '公告内容' }}</span>
      </div>
      <div v-if="!newsItems.length" class="pv-notice__ph">公告内容</div>
    </template>
    <template v-else>
      <img v-if="props.props?.icon" :src="props.props.icon" class="pv-notice__icon" alt="" />
      <span class="pv-notice__tag">公告</span>
      <span class="pv-notice__text">{{ items[0]?.text || '公告内容' }}</span>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const styleName = computed(() => {
  const s = String(props.props?.style || 'bar')
  if (s === 'classic' || s === 'minimal' || s === 'dark') return 'bar'
  return s
})
const newsItems = computed(() => items.value.slice(0, 3))
</script>
<style scoped>
.pv-notice { display:flex; align-items:center; gap:8px; padding:8px 12px; }
.pv-notice--bar { background:#fffbe6; color:#8a6d3b; }
.pv-notice--news { display:block; background:#fff; color:#172033; }
.pv-notice--marquee-card { background:#fff; color:#333; box-shadow:0 2px 8px rgba(23,32,51,.06); }
.pv-notice__head { display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.pv-notice__icon { width:16px; height:16px; object-fit:contain; flex:none; }
.pv-notice__tag { font-size:11px; color:#fff; background:#e6a23c; border-radius:3px; padding:1px 5px; flex:none; }
.pv-notice--news .pv-notice__tag { background:#172033; }
.pv-notice__text { font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; }
.pv-notice__news-row { display:flex; gap:4px; font-size:12px; padding:3px 0; }
.pv-notice__bullet { color:#e6a23c; font-weight:700; }
.pv-notice__news-text { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pv-notice__ph { font-size:12px; color:#999; }
</style>
