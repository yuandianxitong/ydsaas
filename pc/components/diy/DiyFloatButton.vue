<template>
  <div class="diy-float" :class="p.position === 'left-bottom' ? 'is-left' : 'is-right'">
    <template v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="btn">
        <img v-if="it.icon" :src="it.icon" class="icon" /><span v-if="it.text" class="text">{{ it.text }}</span>
      </a>
      <div v-else class="btn">
        <img v-if="it.icon" :src="it.icon" class="icon" /><span v-if="it.text" class="text">{{ it.text }}</span>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
const items = computed(() => (props.props?.items as any[]) || [])
</script>
<style scoped>
.diy-float { position: absolute; bottom: 60px; z-index: 90; display: flex; flex-direction: column; }
.diy-float.is-right { right: 12px; }
.diy-float.is-left { left: 12px; }
.btn { width: 48px; height: 48px; margin-top: 8px; border-radius: 50%; background: rgba(0,0,0,.6); display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; }
.icon { width: 20px; height: 20px; object-fit: contain; }
.text { color: #fff; font-size: 9px; }
</style>
