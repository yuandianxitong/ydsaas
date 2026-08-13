<template>
  <!-- 分类导航（PC，视觉对齐移动端形态：圆图标 + 次级文字）；items 为空不渲染 -->
  <div v-if="items.length && isScroll" class="diy-cat diy-cat--scroll">
    <template v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="item item--scroll">
        <img v-if="it.icon" :src="it.icon" class="icon" /><div v-else class="icon icon--empty" /><span class="name">{{ it.title }}</span>
      </a>
      <div v-else class="item item--scroll">
        <img v-if="it.icon" :src="it.icon" class="icon" /><div v-else class="icon icon--empty" /><span class="name">{{ it.title }}</span>
      </div>
    </template>
  </div>
  <div v-else-if="items.length" class="diy-cat diy-cat--grid" :style="{ gridTemplateColumns: `repeat(${cols}, 1fr)` }">
    <template v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="item">
        <img v-if="it.icon" :src="it.icon" class="icon" /><div v-else class="icon icon--empty" /><span class="name">{{ it.title }}</span>
      </a>
      <div v-else class="item">
        <img v-if="it.icon" :src="it.icon" class="icon" /><div v-else class="icon icon--empty" /><span class="name">{{ it.title }}</span>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const cols = computed(() => props.props?.columns || 5)
const isScroll = computed(() => props.props?.style === 'scroll')
</script>
<style scoped>
.diy-cat { padding: 12px; box-sizing: border-box; }
.diy-cat--grid { display: grid; gap: 8px; }
.diy-cat--scroll { display: flex; overflow-x: auto; }
.item { display: flex; flex-direction: column; align-items: center; text-decoration: none; }
.item--scroll { flex-shrink: 0; width: 64px; }
.icon { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
.icon--empty { background: #f5f5f5; }
.name { font-size: 12px; color: #666; margin-top: 4px; }
</style>
