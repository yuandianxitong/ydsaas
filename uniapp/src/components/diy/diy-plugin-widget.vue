<template>
  <!-- 插件自带渲染器（协议 v1）：type 命中 → 整块交给生成的静态分支宿主（含区块头/交互逻辑）。
       注意：小程序编译器不支持 <component :is>，分发在 sync 生成的宿主组件里展开为静态 v-if 链 -->
  <plugin-renderer-host v-if="hasPluginRenderer" :type="type!" :props="props.props" />

  <!-- 核心通用渲染（5 种）：card-list / single / grid-3 / scroll-x / list（缺省回退） -->
  <view v-else class="diy-pw">
    <view v-if="sectionTitle" class="pw-section">
      <text class="pw-section__title">{{ sectionTitle }}</text>
      <text v-if="moreLink" class="pw-section__more" @tap="diyNavigate(moreLink)">查看更多 →</text>
    </view>
    <!-- card-list: 2 列卡片 -->
    <view v-if="render === 'card-list'" class="pw-cards">
      <template v-for="(it, i) in items" :key="i">
        <view v-if="it.link" @tap="diyNavigate(it.link)" class="pw-card">
          <image v-if="it.image" :src="it.image" mode="aspectFill" class="pw-card__img" />
          <view class="pw-card__body">
            <text class="pw-card__title">{{ it.title }}</text>
            <text v-if="it.desc" class="pw-card__desc">{{ it.desc }}</text>
            <text v-if="it.meta" class="pw-card__meta">{{ it.meta }}</text>
          </view>
        </view>
        <view v-else class="pw-card">
          <image v-if="it.image" :src="it.image" mode="aspectFill" class="pw-card__img" />
          <view class="pw-card__body">
            <text class="pw-card__title">{{ it.title }}</text>
            <text v-if="it.desc" class="pw-card__desc">{{ it.desc }}</text>
            <text v-if="it.meta" class="pw-card__meta">{{ it.meta }}</text>
          </view>
        </view>
      </template>
    </view>

    <!-- single: 单 hero（仅 items[0]） -->
    <view v-else-if="render === 'single' && items.length" class="pw-single">
      <view v-if="items[0].link" @tap="diyNavigate(items[0].link)" class="pw-single__inner">
        <image v-if="items[0].image" :src="items[0].image" mode="aspectFill" class="pw-single__img" />
        <text class="pw-single__title">{{ items[0].title }}</text>
        <text v-if="items[0].desc" class="pw-single__desc">{{ items[0].desc }}</text>
        <text v-if="items[0].meta" class="pw-single__meta">{{ items[0].meta }}</text>
      </view>
      <view v-else class="pw-single__inner">
        <image v-if="items[0].image" :src="items[0].image" mode="aspectFill" class="pw-single__img" />
        <text class="pw-single__title">{{ items[0].title }}</text>
        <text v-if="items[0].desc" class="pw-single__desc">{{ items[0].desc }}</text>
        <text v-if="items[0].meta" class="pw-single__meta">{{ items[0].meta }}</text>
      </view>
    </view>

    <!-- grid-3: 三列 -->
    <view v-else-if="render === 'grid-3'" class="pw-cards pw-cards--3">
      <view v-for="(it, i) in items" :key="i" class="pw-card pw-card--3" @tap="it.link && diyNavigate(it.link)">
        <image v-if="it.image" :src="it.image" mode="aspectFill" class="pw-card__img pw-card__img--3" />
        <view class="pw-card__body">
          <text class="pw-card__title pw-card__title--sm">{{ it.title }}</text>
          <text v-if="it.desc" class="pw-card__desc">{{ it.desc }}</text>
          <text v-if="it.meta" class="pw-card__meta">{{ it.meta }}</text>
        </view>
      </view>
    </view>

    <!-- scroll-x: 横滑 -->
    <scroll-view v-else-if="render === 'scroll-x'" scroll-x class="pw-scroll">
      <view class="pw-scroll__inner">
        <view v-for="(it, i) in items" :key="i" class="pw-card pw-card--scroll" @tap="it.link && diyNavigate(it.link)">
          <image v-if="it.image" :src="it.image" mode="aspectFill" class="pw-card__img" />
          <view class="pw-card__body">
            <text class="pw-card__title pw-card__title--sm">{{ it.title }}</text>
            <text v-if="it.desc" class="pw-card__desc">{{ it.desc }}</text>
          </view>
        </view>
      </view>
    </scroll-view>

    <!-- list（缺省）: 整行 -->
    <view v-else class="pw-list">
      <template v-for="(it, i) in items" :key="i">
        <view v-if="it.link" @tap="diyNavigate(it.link)" class="pw-row">
          <image v-if="it.image" :src="it.image" mode="aspectFill" class="pw-row__img" />
          <view class="pw-row__body">
            <text class="pw-row__title">{{ it.title }}</text>
            <text v-if="it.desc" class="pw-row__desc">{{ it.desc }}</text>
            <text v-if="it.meta" class="pw-row__meta">{{ it.meta }}</text>
          </view>
        </view>
        <view v-else class="pw-row">
          <image v-if="it.image" :src="it.image" mode="aspectFill" class="pw-row__img" />
          <view class="pw-row__body">
            <text class="pw-row__title">{{ it.title }}</text>
            <text v-if="it.desc" class="pw-row__desc">{{ it.desc }}</text>
            <text v-if="it.meta" class="pw-row__meta">{{ it.meta }}</text>
          </view>
        </view>
      </template>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { DIY_PLUGIN_RENDERER_TYPES } from '@/generated/diy-plugin-renderers'

import { diyNavigate } from './navigate'
import PluginRendererHost from './plugin-renderer-host.generated.vue'
import { applyShowPrice, resolveRender, type PwItem } from './pluginWidget'

const props = defineProps<{ props: Record<string, any>; type?: string }>()

// 插件渲染器按 widget type 精确匹配（生成的 type 集合 + 静态分支宿主）；
// 未命中 → 核心通用渲染，render 不在核心 5 种时由 resolveRender 降级 list（三端统一回退契约）
const hasPluginRenderer = computed(() => !!props.type && DIY_PLUGIN_RENDERER_TYPES.has(props.type))

const sectionTitle = computed(() => String(props.props?.section_title || '').trim())
const render = computed(() => resolveRender(props.props?.render))
const items = computed(() => applyShowPrice((props.props?.items as PwItem[]) || [], props.props?.show_price))
const moreLink = computed(() => (props.props?.more_link as string) || '')
</script>

<style lang="scss" scoped>
.diy-pw { width: 100%; }
.pw-section { display: flex; align-items: flex-end; justify-content: space-between; gap: 24rpx; padding: 28rpx 24rpx 10rpx; }
.pw-section__title { display: block; color: #172033; font-size: 34rpx; font-weight: 700; }
.pw-section__more { flex-shrink: 0; color: #8a93a2; font-size: 22rpx; }
/* card-list */
.pw-cards { display: flex; flex-wrap: wrap; padding: 12rpx; box-sizing: border-box; }
.pw-card { width: 50%; box-sizing: border-box; padding: 8rpx; display: block; }
.pw-card__img { width: 100%; height: 200rpx; border-radius: 8rpx; display: block; }
.pw-card__body { padding: 8rpx 4rpx; }
.pw-card__title { font-size: 26rpx; color: #222; display: block; }
.pw-card__desc { font-size: 24rpx; color: #fa3534; display: block; margin-top: 4rpx; }
.pw-card__meta { font-size: 22rpx; color: #999; display: block; margin-top: 4rpx; }
/* list */
.pw-list { padding: 8rpx 16rpx; }
.pw-row { display: flex; align-items: center; padding: 12rpx 0; }
.pw-row__img { width: 120rpx; height: 120rpx; border-radius: 8rpx; margin-right: 16rpx; flex-shrink: 0; }
.pw-row__body { flex: 1; min-width: 0; }
.pw-row__title { font-size: 28rpx; color: #222; display: block; }
.pw-row__desc { font-size: 24rpx; color: #fa3534; display: block; margin-top: 6rpx; }
.pw-row__meta { font-size: 22rpx; color: #999; display: block; margin-top: 4rpx; }
/* single */
.pw-single { padding: 16rpx; }
.pw-single__inner { display: block; }
.pw-single__img { width: 100%; height: 320rpx; border-radius: 12rpx; display: block; }
.pw-single__title { font-size: 32rpx; font-weight: 600; color: #222; display: block; margin-top: 12rpx; }
.pw-single__desc { font-size: 26rpx; color: #fa3534; display: block; margin-top: 6rpx; }
.pw-single__meta { font-size: 24rpx; color: #999; display: block; margin-top: 4rpx; }
/* grid-3 / scroll-x */
.pw-cards--3 .pw-card--3 { width: 33.333%; }
.pw-card__img--3 { height: 140rpx; }
.pw-card__title--sm { font-size: 24rpx; }
.pw-scroll { white-space: nowrap; padding: 12rpx; box-sizing: border-box; }
.pw-scroll__inner { display: flex; }
.pw-card--scroll { width: 240rpx; flex-shrink: 0; padding: 8rpx; }
</style>
