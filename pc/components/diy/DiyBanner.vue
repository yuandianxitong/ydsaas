<template>
  <Swiper
    :modules="[SwiperAutoplay, SwiperPagination]"
    :autoplay="p.autoplay !== false ? { delay: p.interval || 3000, disableOnInteraction: false } : false"
    :pagination="{ clickable: true }"
    :loop="items.length > 1"
    class="diy-banner"
    :style="{ height: `${(p.height || 300) / 2}px` }"
  >
    <SwiperSlide v-for="(it, i) in items" :key="i">
      <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="cell"><img :src="it.image" class="img" /></a>
      <div v-else class="cell"><img :src="it.image" class="img" /></div>
    </SwiperSlide>
  </Swiper>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay as SwiperAutoplay, Pagination as SwiperPagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/pagination'
import { safeLink } from './safeLink'
const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
const items = computed(() => (props.props?.items as any[]) || [])
</script>
<style scoped>
.diy-banner { width: 100%; }
.cell, .img { width: 100%; height: 100%; display: block; }
.img { object-fit: cover; }
</style>
