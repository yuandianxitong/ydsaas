<template>
  <view v-if="layout" class="diy-image-cube diy-image-cube--grid" :style="gridStyle">
    <view
      v-for="(it, i) in displayItems"
      :key="i"
      class="diy-image-cube__cell"
      :style="cellStyle(i)"
      @tap="onTap(it)"
    >
      <image v-if="it.image" :src="it.image" mode="aspectFill" class="diy-image-cube__img" />
      <view v-else class="diy-image-cube__ph" />
    </view>
  </view>
  <view v-else class="diy-image-cube">
    <template v-for="(it, i) in items" :key="i">
      <view class="diy-image-cube__item" :style="legacyItemStyle" @tap="onTap(it)">
        <image v-if="it.image" :src="it.image" mode="aspectFill" class="diy-image-cube__img" />
      </view>
    </template>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { ensureCubeItems, getCubeLayout } from './cubeLayouts'
import { diyNavigate } from './navigate'

const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const layout = computed(() => getCubeLayout(props.props?.layout))
const displayItems = computed(() =>
  layout.value ? ensureCubeItems(items.value, layout.value.slots) : items.value
)
const cols = computed(() => props.props?.cols || 2)
const gapRpx = computed(() => (props.props?.gap || 0) / 2)

const gridStyle = computed(() => {
  const l = layout.value
  if (!l) return {}
  const rowH = Math.max(120, Math.round(400 / l.rows))
  return {
    display: 'grid',
    gridTemplateColumns: `repeat(${l.columns}, 1fr)`,
    gridTemplateRows: `repeat(${l.rows}, ${rowH}rpx)`,
    gap: `${gapRpx.value}rpx`,
  }
})

function cellStyle(i: number) {
  const cell = layout.value?.cells[i]
  if (!cell) return {}
  return { gridColumn: cell.column, gridRow: cell.row }
}

const legacyItemStyle = computed(() => {
  const g = gapRpx.value
  return { width: `${100 / (cols.value || 2)}%`, paddingLeft: `${g}rpx`, paddingRight: `${g}rpx` }
})

function onTap(it: { link?: string }) {
  if (it?.link) diyNavigate(it.link)
}
</script>
<style scoped>
.diy-image-cube { display:flex; flex-wrap:wrap; }
.diy-image-cube--grid { width:100%; }
.diy-image-cube__item { box-sizing:border-box; }
.diy-image-cube__cell { min-width:0; min-height:0; overflow:hidden; border-radius:8rpx; }
.diy-image-cube__img { width:100%; height:100%; display:block; }
.diy-image-cube:not(.diy-image-cube--grid) .diy-image-cube__img { height:200rpx; border-radius:8rpx; }
.diy-image-cube__ph { width:100%; height:100%; background:#e3e7ef; }
</style>
