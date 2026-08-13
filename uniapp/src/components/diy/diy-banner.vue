<template>
  <view class="diy-banner" :class="[`diy-banner--ind-${indicatorPos}`]">
    <swiper
      class="diy-banner__sw"
      :autoplay="p.autoplay !== false"
      :interval="p.interval || 3000"
      circular
      :style="{ height: (p.height || 300) + 'rpx' }"
      @change="onChange"
    >
      <swiper-item v-for="(it, i) in items" :key="i">
        <view v-if="it.link" @tap="diyNavigate(it.link)">
          <image :src="it.image" mode="aspectFill" class="diy-banner__img" />
        </view>
        <image v-else :src="it.image" mode="aspectFill" class="diy-banner__img" />
      </swiper-item>
    </swiper>
    <view
      v-if="indicatorStyle !== 'none' && items.length > 1"
      class="diy-banner__ind"
      :class="[`diy-banner__ind--${indicatorStyle}`, `diy-banner__ind--${indicatorPos}`]"
    >
      <template v-if="indicatorStyle === 'number'">
        <text class="diy-banner__num">{{ current + 1 }}/{{ items.length }}</text>
      </template>
      <template v-else>
        <view
          v-for="(_, i) in items"
          :key="i"
          class="diy-banner__dot"
          :class="{ 'diy-banner__dot--on': i === current }"
        />
      </template>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { diyNavigate } from './navigate'

const props = defineProps<{ props: Record<string, any> }>()
const p = computed(() => props.props || {})
const items = computed(() => (props.props?.items as any[]) || [])
const indicatorStyle = computed(() => String(p.value.indicator_style || 'dot'))
const indicatorPos = computed(() => String(p.value.indicator_position || 'inside-bottom'))
const current = ref(0)
function onChange(e: any) {
  current.value = Number(e?.detail?.current || 0)
}
</script>

<style lang="scss" scoped>
.diy-banner {
  width: 100%;
  position: relative;
  &--ind-outside-bottom {
    padding-bottom: 28rpx;
  }
  &__sw {
    width: 100%;
  }
  &__img {
    width: 100%;
    height: 100%;
  }
  &__ind {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10rpx;
    z-index: 2;
    &--inside-bottom {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 16rpx;
    }
    &--outside-bottom {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
    }
    &--inside-right {
      position: absolute;
      right: 16rpx;
      top: 50%;
      transform: translateY(-50%);
      flex-direction: column;
    }
  }
  &__dot {
    width: 12rpx;
    height: 12rpx;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.55);
    &--on {
      background: #ffffff;
      box-shadow: 0 0 6rpx rgba(0, 0, 0, 0.2);
    }
  }
  &__ind--line &__dot {
    width: 24rpx;
    height: 6rpx;
    border-radius: 6rpx;
  }
  &__ind--line &__dot--on {
    width: 32rpx;
  }
  &__num {
    font-size: 20rpx;
    color: #fff;
    background: rgba(0, 0, 0, 0.35);
    border-radius: 20rpx;
    padding: 4rpx 14rpx;
  }
}
</style>
