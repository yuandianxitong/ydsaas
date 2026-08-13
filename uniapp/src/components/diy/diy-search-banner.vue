<template>
  <view class="diy-sb" :class="[`diy-sb--${styleName}`, { 'diy-sb--sticky-on': sticky }]">
    <view v-if="sticky" class="diy-sb__sentinel" />
    <!-- 当前轮播图模糊背景：自上而下渐隐，底部不留色块 -->
    <view class="diy-sb__blur" :style="blurFallbackStyle">
      <image v-if="blurSrc" :src="blurSrc" mode="aspectFill" class="diy-sb__blur-img" />
      <view class="diy-sb__blur-fade" />
    </view>

    <view
      class="diy-sb__head"
      :class="{
        'diy-sb__head--stuck': stuck,
        'diy-sb__head--sticky': sticky,
        'diy-sb__head--no-tabs': !showTabs || !tabs.length,
      }"
      :style="headStyle"
    >
      <view class="diy-sb__search" :style="searchGutterStyle">
        <image
          v-if="brandMode === 'logo' && displayLogo"
          :src="displayLogo"
          mode="heightFix"
          class="diy-sb__logo"
        />
        <text v-else-if="brandMode === 'text' && brandText" class="diy-sb__brand">{{ brandText }}</text>
        <view class="diy-sb__box" @tap="onSearchTap">
          <swiper
            v-if="hotwords.length"
            class="diy-sb__hw"
            vertical
            autoplay
            circular
            :interval="hotwordInterval"
            :indicator-dots="false"
            @change="onHotwordChange"
          >
            <swiper-item v-for="(hw, i) in hotwords" :key="i">
              <text class="diy-sb__ph">{{ hw.text || placeholder }}</text>
            </swiper-item>
          </swiper>
          <text v-else class="diy-sb__ph">{{ placeholder }}</text>
        </view>
      </view>
      <scroll-view v-if="showTabs && tabs.length" scroll-x class="diy-sb__tabs" :show-scrollbar="false">
        <view class="diy-sb__tabs-inner">
          <text
            v-for="(tab, i) in tabs"
            :key="i"
            class="diy-sb__tab"
            @tap="tab.link && diyNavigate(tab.link)"
          >{{ tab.text }}</text>
        </view>
      </scroll-view>
    </view>

    <view class="diy-sb__stage">
      <swiper
        class="diy-sb__sw"
        :style="{ height: heightRpx + 'rpx' }"
        :autoplay="autoplay"
        :interval="interval"
        circular
        :previous-margin="peekMargin"
        :next-margin="peekMargin"
        @change="onChange"
      >
        <swiper-item v-for="(it, i) in items" :key="i" class="diy-sb__si">
          <view class="diy-sb__slide" :class="{ 'diy-sb__slide--card': styleName === 'card' }" @tap="onSlide(it)">
            <image v-if="it.image" :src="it.image" mode="aspectFill" class="diy-sb__img" />
            <view v-else class="diy-sb__ph-slide" />
          </view>
        </swiper-item>
      </swiper>
      <view
        v-if="indicatorStyle !== 'none' && items.length > 1"
        class="diy-sb__ind"
      >
        <text v-if="indicatorStyle === 'number'" class="diy-sb__num">{{ current + 1 }}/{{ items.length }}</text>
        <template v-else>
          <view
            v-for="(_, i) in items"
            :key="i"
            class="diy-sb__dot"
            :class="{ 'diy-sb__dot--on': i === current, 'diy-sb__dot--line': indicatorStyle === 'line' }"
          />
        </template>
      </view>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, onMounted, onUnmounted, ref } from 'vue'

import { getStatusBarHeight, isApp, isH5, isWeixin } from '@/utils/platform'

import { diyNavigate } from './navigate'

function normalizeStyle(raw?: string): 'card' | 'peek' {
  const v = String(raw || 'card')
  if (v === 'peek' || v === 'stacked') return 'peek'
  return 'card'
}

const props = defineProps<{ props: Record<string, any>; isFirst?: boolean }>()

const styleName = computed(() => normalizeStyle(props.props?.style))
const brandMode = computed(() => String(props.props?.brand_mode || 'logo'))
const brandText = computed(() => String(props.props?.brand_text || '').trim())
const logo = computed(() => String(props.props?.logo || ''))
const logoSticky = computed(() => String(props.props?.logo_sticky || ''))
const placeholder = computed(() => String(props.props?.placeholder || '请输入搜索词'))
const searchLink = computed(() => String(props.props?.search_link || ''))
const sticky = computed(() => !!props.props?.sticky)
const showTabs = computed(() => props.props?.show_tabs !== false && props.props?.show_tabs !== 0)
const tabs = computed(() =>
  (Array.isArray(props.props?.tabs) ? props.props.tabs : []).filter((t: any) => String(t?.text || '').trim())
)
const hotwords = computed(() =>
  (Array.isArray(props.props?.hotwords) ? props.props.hotwords : []).filter((h: any) => String(h?.text || '').trim())
)
const hotwordInterval = computed(() => Number(props.props?.hotword_interval) || 3000)
const heightRpx = computed(() => Number(props.props?.height) || 360)
const autoplay = computed(() => props.props?.autoplay !== false)
const interval = computed(() => Number(props.props?.interval) || 3000)
const indicatorStyle = computed(() => String(props.props?.indicator_style || 'dot'))
const items = computed(() => (Array.isArray(props.props?.items) ? props.props.items : []))
const current = ref(0)
const hotwordIndex = ref(0)
const stuck = ref(false)

/** 状态栏占位仅小程序/App；H5 浏览器无刘海区，加高会顶出空白 */
const statusPad = computed(() => {
  if (!props.isFirst || isH5()) return 0
  if (isWeixin() || isApp()) return getStatusBarHeight()
  return 0
})
const displayLogo = computed(() => {
  if (stuck.value && logoSticky.value) return logoSticky.value
  return logo.value
})

const theme = computed(() => {
  const it = items.value[current.value]
  const c = String(it?.theme_color || '').trim()
  if (c) return c
  return String(props.props?.theme_color || '#ff6034')
})

const blurSrc = computed(() => String(items.value[current.value]?.image || items.value[0]?.image || ''))
const useBlur = computed(() => props.props?.blur_bg !== false && props.props?.blur_bg !== 0)

const blurFallbackStyle = computed(() => {
  if (blurSrc.value && useBlur.value) return {}
  // 无图时主题色同样自上而下减淡
  return {
    background: `linear-gradient(180deg, ${theme.value} 0%, ${theme.value}aa 42%, transparent 100%)`,
  }
})

/** 样式二：两侧露出相邻图；样式一：用外层 padding 留白 */
const peekMargin = computed(() => (styleName.value === 'peek' ? '36rpx' : '0rpx'))

function capsuleRightPx(): number {
  // 仅微信小程序需避开右上角胶囊；H5/App 用常规边距
  if (!isWeixin()) return 24
  try {
    const menu = (uni as any).getMenuButtonBoundingClientRect?.()
    const sys = uni.getSystemInfoSync?.()
    if (menu && sys?.windowWidth) {
      return Math.max(12, Math.ceil(sys.windowWidth - menu.left) + 8)
    }
  } catch {
    /* ignore */
  }
  return 24
}

const headStyle = computed(() => {
  const css: Record<string, string> = {
    paddingTop: statusPad.value + 'px',
  }
  if (stuck.value) {
    css.background = theme.value
  }
  return css
})

const searchGutterStyle = computed(() => ({
  paddingRight: capsuleRightPx() + 'px',
}))

let observer: UniApp.IntersectionObserver | null = null
onMounted(() => {
  if (!sticky.value) return
  const inst = getCurrentInstance()
  observer = uni.createIntersectionObserver(inst?.proxy as any, { thresholds: [0, 1] })
  observer.relativeToViewport({ top: -statusPad.value }).observe('.diy-sb__sentinel', (res) => {
    stuck.value = (res.intersectionRatio || 0) <= 0
  })
})
onUnmounted(() => {
  observer?.disconnect()
  observer = null
})

function onChange(e: any) {
  current.value = Number(e?.detail?.current || 0)
}
function onHotwordChange(e: any) {
  hotwordIndex.value = Number(e?.detail?.current || 0)
}
function onSearchTap() {
  const hw = hotwords.value[hotwordIndex.value]
  if (hw?.link) {
    diyNavigate(hw.link)
    return
  }
  if (searchLink.value) diyNavigate(searchLink.value)
}
function onSlide(it: { link?: string }) {
  if (it?.link) diyNavigate(it.link)
}
</script>

<style scoped>
.diy-sb {
  position: relative;
  width: 100%;
  overflow: hidden;
  padding-bottom: 16rpx;
}
.diy-sb__blur {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  /* 只铺到组件中上部，底部交给渐隐层淡出 */
  height: 92%;
  z-index: 0;
  overflow: hidden;
  pointer-events: none;
  -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 38%, rgba(0, 0, 0, 0.35) 68%, transparent 100%);
  mask-image: linear-gradient(180deg, #000 0%, #000 38%, rgba(0, 0, 0, 0.35) 68%, transparent 100%);
}
.diy-sb__blur-img {
  width: 100%;
  height: 100%;
  filter: blur(48px);
  transform: scale(1.3);
  opacity: 0.95;
}
/* 小程序 mask 兼容弱时再叠一层白渐变，保证底部干净淡出 */
.diy-sb__blur-fade {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 55%;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.55) 45%, #fff 100%);
}
.diy-sb__sentinel {
  position: absolute;
  top: 0;
  left: 0;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}
.diy-sb__head {
  position: relative;
  z-index: 3;
}
.diy-sb--sticky-on .diy-sb__head--sticky {
  position: sticky;
  top: 0;
  z-index: 20;
}
.diy-sb__head--stuck {
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.08);
}
/* 隐藏分类条时加大与轮播的间距 */
.diy-sb__head--no-tabs .diy-sb__search {
  padding-bottom: 20rpx;
}
.diy-sb__search {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 16rpx 24rpx 12rpx;
  box-sizing: border-box;
}
.diy-sb__logo {
  height: 44rpx;
  width: auto;
  max-width: 160rpx;
  flex-shrink: 0;
}
.diy-sb__brand {
  color: #fff;
  font-size: 30rpx;
  font-weight: 700;
  flex-shrink: 0;
  max-width: 160rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.25);
}
.diy-sb__box {
  flex: 1;
  min-width: 0;
  height: 64rpx;
  border-radius: 999rpx;
  background: rgba(255, 255, 255, 0.94);
  display: flex;
  align-items: center;
  padding: 0 28rpx;
  box-sizing: border-box;
  overflow: hidden;
}
.diy-sb__hw {
  width: 100%;
  height: 64rpx;
}
.diy-sb__ph {
  color: #999;
  font-size: 26rpx;
  line-height: 64rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.diy-sb__tabs {
  width: 100%;
  white-space: nowrap;
  padding: 0 16rpx 12rpx;
  box-sizing: border-box;
}
.diy-sb__tabs-inner {
  display: inline-flex;
  gap: 8rpx;
  padding-right: 24rpx;
}
.diy-sb__tab {
  display: inline-block;
  padding: 8rpx 20rpx;
  font-size: 24rpx;
  color: rgba(255, 255, 255, 0.96);
  text-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.2);
  background: rgba(255, 255, 255, 0.18);
  border-radius: 999rpx;
}
.diy-sb__stage {
  position: relative;
  z-index: 1;
}
/* 样式一：左右留白 */
.diy-sb--card .diy-sb__stage {
  padding: 0 24rpx;
}
.diy-sb--card .diy-sb__slide--card {
  border-radius: 20rpx;
  overflow: hidden;
}
/* 样式二：previous/next-margin 露边，卡片间略留缝 */
.diy-sb--peek .diy-sb__si {
  padding: 0 8rpx;
  box-sizing: border-box;
}
.diy-sb--peek .diy-sb__slide {
  border-radius: 20rpx;
  overflow: hidden;
  height: 100%;
}
.diy-sb__sw {
  width: 100%;
}
.diy-sb__slide,
.diy-sb__img,
.diy-sb__ph-slide {
  width: 100%;
  height: 100%;
}
.diy-sb__ph-slide {
  background: rgba(0, 0, 0, 0.06);
}
.diy-sb__ind {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 16rpx;
  z-index: 2;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10rpx;
  pointer-events: none;
}
.diy-sb__dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.55);
}
.diy-sb__dot--on {
  background: #ff4d4f;
}
.diy-sb__dot--line {
  width: 24rpx;
  height: 6rpx;
  border-radius: 4rpx;
}
.diy-sb__num {
  font-size: 22rpx;
  color: #fff;
  background: rgba(0, 0, 0, 0.35);
  border-radius: 20rpx;
  padding: 2rpx 12rpx;
}
</style>
