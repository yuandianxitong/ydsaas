<template>
  <div v-if="layout" class="diy-image-cube diy-image-cube--grid" :style="gridStyle">
    <component
      :is="safeLink(it.link) ? 'a' : 'div'"
      v-for="(it, i) in displayItems"
      :key="i"
      class="cell"
      :style="cellStyle(i)"
      v-bind="safeLink(it.link) ? { href: safeLink(it.link), rel: 'noopener noreferrer' } : {}"
    >
      <img v-if="it.image" :src="it.image" class="img" alt="" />
      <div v-else class="ph" />
    </component>
  </div>
  <div v-else class="diy-image-cube">
    <template v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="item" :style="legacyItemStyle">
        <img :src="it.image" class="img" alt="" />
      </a>
      <div v-else class="item" :style="legacyItemStyle">
        <img v-if="it.image" :src="it.image" class="img" alt="" />
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { ensureCubeItems, getCubeLayout } from './cubeLayouts'
import { safeLink } from './safeLink'

const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const layout = computed(() => getCubeLayout(props.props?.layout))
const displayItems = computed(() =>
  layout.value ? ensureCubeItems(items.value, layout.value.slots) : items.value
)
const cols = computed(() => props.props?.cols || 2)
const gapPx = computed(() => (props.props?.gap || 0) / 4)

const gridStyle = computed(() => {
  const l = layout.value
  if (!l) return {}
  const rowH = Math.max(56, Math.round(200 / l.rows) / 2)
  return {
    display: 'grid',
    gridTemplateColumns: `repeat(${l.columns}, 1fr)`,
    gridTemplateRows: `repeat(${l.rows}, ${rowH}px)`,
    gap: `${gapPx.value}px`,
  }
})

function cellStyle(i: number) {
  const cell = layout.value?.cells[i]
  if (!cell) return {}
  return { gridColumn: cell.column, gridRow: cell.row }
}

const legacyItemStyle = computed(() => {
  const g = gapPx.value
  return { width: `${100 / (cols.value || 2)}%`, paddingLeft: `${g}px`, paddingRight: `${g}px` }
})
</script>
<style scoped>
.diy-image-cube { display: flex; flex-wrap: wrap; }
.diy-image-cube--grid { width: 100%; }
.item { box-sizing: border-box; display: block; }
.cell { min-width: 0; min-height: 0; overflow: hidden; border-radius: 4px; display: block; }
.img { width: 100%; height: 100%; object-fit: cover; display: block; }
.diy-image-cube:not(.diy-image-cube--grid) .img { height: 100px; border-radius: 4px; }
.ph { width: 100%; height: 100%; background: #e3e7ef; }
</style>
