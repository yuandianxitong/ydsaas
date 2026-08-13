<template>
  <!-- 分类导航（视觉对齐元点Shop diy-category-nav：88rpx 圆图标 + 24rpx 次级文字）；items 为空不渲染 -->
  <view v-if="items.length && isScroll" class="diy-cat">
    <scroll-view scroll-x class="diy-cat__scroll">
      <view class="diy-cat__row">
        <view v-for="(it, i) in items" :key="i" class="diy-cat__item diy-cat__item--scroll" @tap="it.link && diyNavigate(it.link)">
          <image v-if="it.icon" :src="it.icon" mode="aspectFill" class="diy-cat__icon" />
          <view v-else class="diy-cat__icon diy-cat__icon--empty" />
          <text class="diy-cat__name">{{ it.title }}</text>
        </view>
      </view>
    </scroll-view>
  </view>
  <view v-else-if="items.length" class="diy-cat diy-cat--grid" :style="{ gridTemplateColumns: `repeat(${cols}, 1fr)` }">
    <view v-for="(it, i) in items" :key="i" class="diy-cat__item" @tap="it.link && diyNavigate(it.link)">
      <image v-if="it.icon" :src="it.icon" mode="aspectFill" class="diy-cat__icon" />
      <view v-else class="diy-cat__icon diy-cat__icon--empty" />
      <text class="diy-cat__name">{{ it.title }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { diyNavigate } from './navigate'
const props = defineProps<{ props: Record<string, any> }>()
const items = computed(() => (props.props?.items as any[]) || [])
const cols = computed(() => props.props?.columns || 5)
const isScroll = computed(() => props.props?.style === 'scroll')
</script>

<style scoped>
.diy-cat { padding: 24rpx; box-sizing: border-box; }
.diy-cat--grid { display: grid; gap: 16rpx; }
.diy-cat__scroll { white-space: nowrap; }
.diy-cat__row { display: flex; }
.diy-cat__item { display: flex; flex-direction: column; align-items: center; }
.diy-cat__item--scroll { flex-shrink: 0; width: 128rpx; }
.diy-cat__icon { width: 88rpx; height: 88rpx; border-radius: 50%; }
.diy-cat__icon--empty { background: #f5f5f5; }
.diy-cat__name { font-size: 24rpx; color: #666; margin-top: 8rpx; }
</style>
