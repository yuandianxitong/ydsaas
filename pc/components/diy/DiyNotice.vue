<template>
  <div class="diy-notice">
    <img v-if="p.icon" :src="p.icon" class="icon" />
    <div v-if="items.length" class="vp">
      <a v-if="safeLink(cur.link)" :href="safeLink(cur.link)" rel="noopener noreferrer" class="text">{{ cur.text }}</a>
      <span v-else class="text">{{ cur.text }}</span>
    </div>
    <span v-else class="text ph">公告</span>
  </div>
</template>
<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
const items = computed(() => (props.props?.items as any[]) || [])
const idx = ref(0)
const cur = computed(() => items.value[idx.value] || { text: '' })
let timer: any = null
onMounted(() => {
  if (items.value.length > 1) {
    timer = setInterval(() => { idx.value = (idx.value + 1) % items.value.length }, p.value.speed || 3000)
  }
})
onUnmounted(() => timer && clearInterval(timer))
</script>
<style scoped>
.diy-notice { display: flex; align-items: center; padding: 6px 10px; background: #fffbe6; }
.icon { width: 16px; height: 16px; margin-right: 6px; object-fit: contain; }
.vp { flex: 1; height: 20px; overflow: hidden; }
.text { font-size: 12px; color: #8a6d3b; line-height: 20px; text-decoration: none; }
.ph { color: #c0a16b; }
</style>
