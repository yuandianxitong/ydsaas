<template>
  <div :style="dividerStyle" />
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const dividerStyle = computed(() => {
  const p = props.props || {}
  const h = `${(p.height ?? 20) / 2}px` // rpx ÷2
  if (p.type === 'line') return { width: '100%', height: '1px', backgroundColor: p.color || '#eee', margin: `${h} 0` }
  return { width: '100%', height: h }
})
</script>
