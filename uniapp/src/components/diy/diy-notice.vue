<template>
  <view class="diy-notice" :class="`diy-notice--${styleName}`">
    <template v-if="styleName === 'news'">
      <view class="diy-notice__head">
        <image v-if="props.props?.icon" :src="props.props.icon" class="diy-notice__icon" />
        <text class="diy-notice__tag">公告</text>
      </view>
      <view v-if="!items.length" class="diy-notice__ph">暂无公告</view>
      <view
        v-for="(it, i) in newsItems"
        :key="i"
        class="diy-notice__news-row"
        @tap="it.link && diyNavigate(it.link)"
      >
        <text class="diy-notice__bullet">·</text>
        <text class="diy-notice__news-text">{{ it.text }}</text>
      </view>
    </template>

    <template v-else-if="styleName === 'marquee-card' || scrollMode === 'marquee'">
      <image v-if="props.props?.icon" :src="props.props.icon" class="diy-notice__icon" />
      <text class="diy-notice__tag">公告</text>
      <view class="diy-notice__marquee" @tap="onMarqueeTap">
        <text class="diy-notice__marquee-text" :style="marqueeAnim">{{ marqueeText || '公告' }}</text>
      </view>
    </template>

    <template v-else>
      <image v-if="props.props?.icon" :src="props.props.icon" class="diy-notice__icon" />
      <text class="diy-notice__tag">公告</text>
      <swiper
        v-if="items.length"
        vertical
        :autoplay="items.length > 1"
        :interval="props.props?.speed || 3000"
        :circular="items.length > 2"
        class="diy-notice__sw"
      >
        <swiper-item v-for="(it, i) in items" :key="i">
          <view v-if="it.link" @tap="diyNavigate(it.link)" class="diy-notice__text">{{ it.text }}</view>
          <text v-else class="diy-notice__text">{{ it.text }}</text>
        </swiper-item>
      </swiper>
      <text v-else class="diy-notice__text diy-notice__ph">公告</text>
    </template>
  </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => props.props?.items || [])
const styleName = computed(() => {
  const s = String(props.props?.style || 'bar')
  // 兼容旧 classic/minimal/dark
  if (s === 'classic' || s === 'minimal' || s === 'dark') return 'bar'
  return s
})
const scrollMode = computed(() => String(props.props?.scroll_mode || 'vertical'))
const newsItems = computed(() => items.value.slice(0, 3))
const marqueeText = computed(() => items.value.map((it: any) => it.text).filter(Boolean).join('　　'))
const marqueeAnim = computed(() => {
  const ms = Math.max(3000, Number(props.props?.speed) || 3000) * Math.max(1, items.value.length)
  return { animationDuration: `${ms}ms` }
})
function onMarqueeTap() {
  const first = items.value.find((it: any) => it.link)
  if (first?.link) diyNavigate(first.link)
}
</script>
<style scoped>
.diy-notice { display:flex; align-items:center; padding:12rpx 20rpx; box-sizing:border-box; }
.diy-notice--bar { background:#fffbe6; color:#8a6d3b; }
.diy-notice--news {
  display:block; background:#fff; color:#172033;
  padding:20rpx 24rpx;
}
.diy-notice--marquee-card {
  background:#fff; color:#333;
  box-shadow: 0 6rpx 20rpx rgba(23,32,51,0.06);
}
.diy-notice__head { display:flex; align-items:center; margin-bottom:12rpx; }
.diy-notice__icon { width:32rpx; height:32rpx; margin-right:12rpx; flex-shrink:0; }
.diy-notice__tag {
  font-size:20rpx; border-radius:6rpx; padding:2rpx 10rpx; margin-right:12rpx;
  flex-shrink:0; line-height:1.4; color:#fff; background:#e6a23c;
}
.diy-notice--news .diy-notice__tag { background:#172033; }
.diy-notice__sw { height:40rpx; flex:1; min-width:0; }
.diy-notice__text { font-size:24rpx; line-height:40rpx; }
.diy-notice__ph { color:inherit; opacity:0.55; font-size:24rpx; }
.diy-notice__news-row { display:flex; gap:8rpx; padding:8rpx 0; }
.diy-notice__bullet { color:#e6a23c; font-weight:700; }
.diy-notice__news-text {
  font-size:26rpx; color:#172033; flex:1; min-width:0;
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.diy-notice__marquee { flex:1; min-width:0; overflow:hidden; height:40rpx; }
.diy-notice__marquee-text {
  display:inline-block; white-space:nowrap; font-size:24rpx; line-height:40rpx;
  padding-left:100%;
  animation-name: diy-notice-marquee;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
}
@keyframes diy-notice-marquee {
  0% { transform: translateX(0); }
  100% { transform: translateX(-100%); }
}
</style>
