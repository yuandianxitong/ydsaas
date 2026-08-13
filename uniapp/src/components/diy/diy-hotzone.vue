<template>
  <view class="diy-hz">
    <view v-if="image" class="diy-hz__box">
      <image :src="image" mode="widthFix" class="diy-hz__img" />
      <view
        v-for="(a, i) in areas"
        :key="a.id || i"
        class="diy-hz__area"
        :style="{
          left: a.left + '%',
          top: a.top + '%',
          width: a.width + '%',
          height: a.height + '%',
        }"
        @tap.stop="onTap(a)"
      />
    </view>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { diyNavigate } from './navigate'

const props = defineProps<{ props: Record<string, any> }>()
const image = computed(() => String(props.props?.image || ''))
const areas = computed(() => (Array.isArray(props.props?.areas) ? props.props.areas : []))

function onTap(a: { link?: string }) {
  if (a?.link) diyNavigate(a.link)
}
</script>
<style scoped>
.diy-hz { width: 100%; }
.diy-hz__box { position: relative; width: 100%; }
.diy-hz__img { width: 100%; display: block; }
.diy-hz__area { position: absolute; z-index: 2; }
</style>
