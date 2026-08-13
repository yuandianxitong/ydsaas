<template>
  <div class="diy-hz">
    <div v-if="image" class="diy-hz__box">
      <img :src="image" class="diy-hz__img" alt="" />
      <component
        :is="safeLink(a.link) ? 'a' : 'div'"
        v-for="(a, i) in areas"
        :key="a.id || i"
        class="diy-hz__area"
        :style="{
          left: a.left + '%',
          top: a.top + '%',
          width: a.width + '%',
          height: a.height + '%',
        }"
        v-bind="safeLink(a.link) ? { href: safeLink(a.link), rel: 'noopener noreferrer' } : {}"
      />
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { safeLink } from './safeLink'

const props = defineProps<{ props: Record<string, any> }>()
const image = computed(() => String(props.props?.image || ''))
const areas = computed(() => (Array.isArray(props.props?.areas) ? props.props.areas : []))
</script>
<style scoped>
.diy-hz { width: 100%; }
.diy-hz__box { position: relative; width: 100%; }
.diy-hz__img { width: 100%; height: auto; display: block; }
.diy-hz__area { position: absolute; z-index: 2; display: block; }
</style>
