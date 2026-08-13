<template>
  <div
    v-if="layout"
    class="pv-cube pv-cube--grid"
    :style="gridStyle"
  >
    <div
      v-for="(it, i) in displayItems"
      :key="i"
      class="pv-cube__cell"
      :style="cellStyle(i)"
    >
      <img v-if="it.image" :src="it.image" class="pv-cube__img" alt="" />
      <div v-else class="pv-cube__ph" />
    </div>
  </div>
  <div v-else class="pv-cube">
    <div v-for="(it, i) in items" :key="i" class="pv-cube__item" :style="legacyItemStyle">
      <img v-if="it.image" :src="it.image" class="pv-cube__img" alt="" />
      <div v-else class="pv-cube__ph" />
    </div>
    <div v-if="!items.length" class="pv-empty">图片魔方</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { ensureCubeItems, getCubeLayout } from '../cubeLayouts'

const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
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
.pv-cube { display:flex; flex-wrap:wrap; }
.pv-cube--grid { display:grid; width:100%; }
.pv-cube__item { box-sizing:border-box; }
.pv-cube__cell { min-width:0; min-height:0; overflow:hidden; border-radius:4px; }
.pv-cube__img { width:100%; height:100%; object-fit:cover; display:block; }
.pv-cube:not(.pv-cube--grid) .pv-cube__img { height:100px; border-radius:4px; }
.pv-cube__ph { width:100%; height:100%; min-height:40px; background:#e3e7ef; }
.pv-cube:not(.pv-cube--grid) .pv-cube__ph { height:100px; border-radius:4px; }
.pv-empty { width:100%; display:flex; align-items:center; justify-content:center; height:150px; color:#9aa4b2; font-size:12px; background:#eef1f6; border-radius:4px; }
</style>
