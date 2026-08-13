<template>
  <view class="home-page">
    <!-- 配置加载中：先显示加载态，避免默认内容→装修首页来回闪 -->
    <view v-if="!ready" class="home-loading">
      <view class="loading-spinner" />
      <text class="loading-text">加载中...</text>
    </view>
    <template v-else>
    <view
      v-if="homeDecoration && homeDecoration.components && homeDecoration.components.length"
      class="diy-home"
      :style="diyPageStyle"
    >
      <view
        v-if="pageSettings.show_header !== false"
        class="diy-page-titlebar"
        :style="{ backgroundColor: pageSettings.background_color || '' }"
      >
        <text class="diy-page-titlebar__text">{{ pageTitle }}</text>
      </view>
      <DiyRenderer
        :components="homeDecoration.components"
        :page-settings="homeDecoration.page_settings"
      />
      <DiyPopupAd :ad="pageSettings.popup_ad" page-key="home" />
    </view>
    <template v-else>
    <view class="home-fallback-titlebar">
      <text class="home-fallback-titlebar__text">首页</text>
    </view>
    <view class="home-body">
      <!-- Banner -->
      <view v-if="bannerList.length > 0" class="banner-section">
        <swiper
          class="banner-swiper"
          :autoplay="true"
          :interval="4000"
          :circular="true"
          indicator-dots
          indicator-active-color="#2979ff"
          indicator-color="rgba(255,255,255,0.6)"
        >
          <swiper-item v-for="(item, index) in bannerList" :key="index" @tap="item.url && goPage(item.url)">
            <image class="banner-image" :src="appStore.getImageUrl(item.image)" mode="aspectFill" />
          </swiper-item>
        </swiper>
      </view>

      <!-- Function Grid -->
      <view class="grid-section">
        <view class="grid-card">
          <view class="grid-item" @tap="goPage('/modules/feedback/pages/feedback')">
            <view class="grid-icon icon-bg-green">
              <text class="i-ri-message-2-line" />
            </view>
            <text class="grid-text">反馈</text>
          </view>
          <view class="grid-item" @tap="goMessageTab">
            <view class="grid-icon icon-bg-orange">
              <text class="i-ri-mail-line" />
            </view>
            <text class="grid-text">消息</text>
          </view>
          <view class="grid-item" @tap="goPage('/modules/agreement/pages/agreement?code=user_agreement')">
            <view class="grid-icon icon-bg-purple">
              <text class="i-ri-file-text-line" />
            </view>
            <text class="grid-text">协议</text>
          </view>
          <view v-if="hasShop" class="grid-item" @tap="goPage('/modules/shop/pages/goods/list/index')">
            <view class="grid-icon icon-bg-blue">
              <text class="i-ri-shopping-cart-2-line" />
            </view>
            <text class="grid-text">商城</text>
          </view>
        </view>
      </view>
    </view>
    </template>
    </template>
    <AppTabBar current="pages/index/index" />
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onShow, onShareAppMessage } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'
import { useMobileConfigStore } from '@/store/mobile-config.store'
import DiyRenderer from '@/components/diy/DiyRenderer.vue'
import DiyPopupAd from '@/components/diy/DiyPopupAd.vue'
import AppTabBar from '@/components/tabbar/AppTabBar.vue'
import pagesJson from '@/pages.json'
import { provideMemberStats } from '@/hooks/useMemberStats'

const appStore = useAppStore()
const mobileConfigStore = useMobileConfigStore()
const { refresh: refreshMemberStats } = provideMemberStats()

// pages.json 由构建前钩子按租户权益生成：未授权 shop 插件时分包不存在，入口必须隐藏
const hasShop = (pagesJson.subPackages ?? []).some(
  (sp: { root: string }) => sp.root === 'modules/shop'
)

// 移动端配置加载完成前显示加载态（loaded：注入即 true / stub 模式等 API）
const ready = computed(() => mobileConfigStore.loaded)
const homeDecoration = computed(() => mobileConfigStore.config.home_decoration ?? null)
const pageSettings = computed(() => (homeDecoration.value?.page_settings as Record<string, any>) || {})
const pageTitle = computed(
  () => String((homeDecoration.value as any)?.title || pageSettings.value.title || '首页')
)
const diyPageStyle = computed(() => {
  const s = pageSettings.value
  const style: Record<string, string> = {}
  if (s.background_color) style.backgroundColor = s.background_color
  if (s.background_image) {
    style.backgroundImage = `url(${appStore.getImageUrl(s.background_image)})`
    style.backgroundSize = 'cover'
    style.backgroundPosition = 'center'
  }
  return style
})

onShareAppMessage(() => ({
  title: mobileConfigStore.shareTitle,
  imageUrl: mobileConfigStore.shareImage || undefined,
}))

const bannerList = ref<{ image: string; url?: string }[]>([])

function goPage(url: string) {
  uni.navigateTo({ url })
}

function goMessageTab() {
  uni.switchTab({ url: '/pages/message/index' })
}

async function loadData() {
  // Ensure config is loaded
  await appStore.getConfig()

  // Load banner from config
  const config = appStore.config
  if (config?.banner_list && Array.isArray(config.banner_list) && config.banner_list.length > 0) {
    bannerList.value = config.banner_list
  }
}

/**
 * 启动 redirect：租户配置了 home_page 时跳转到对应页面。
 * - 只在第一次 onShow 时执行，避免 redirect 后用户手动返回首页时再次跳走。
 * - 须在 soft-refresh load() 完成后再读 store，避免用过期的构建注入值。
 */
let homeRedirected = false
function maybeRedirectHome() {
  if (homeRedirected) return
  homeRedirected = true
  const homePage = mobileConfigStore.homePage
  if (!homePage) return
  // 内置「首页」(pages/index/index) 就是当前页，无需跳转（避免 index→index 自跳）
  if (homePage.replace(/^\//, '') === 'pages/index/index') return
  const url = homePage.startsWith('/') ? homePage : '/' + homePage
  uni.redirectTo({
    url,
    fail: (err) => console.warn('[mobile-config] home redirect failed', err),
  })
}

onShow(() => {
  mobileConfigStore.load().then(() => {
    maybeRedirectHome()
    // 首页装修树拿到之后再拉统计（角标/资产）
    refreshMemberStats(homeDecoration.value?.components)
  })
  loadData()
})</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.home-page {
  min-height: 100vh;
  background-color: $bg-color;
  // 给自定义底部 tabBar 预留空间（约 50px + 安全区）
  padding-bottom: calc(50px + constant(safe-area-inset-bottom));
  padding-bottom: calc(50px + env(safe-area-inset-bottom));
}

.diy-home {
  min-height: 100vh;
}

.diy-page-titlebar,
.home-fallback-titlebar {
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  padding-top: var(--status-bar-height, 0);
  box-sizing: content-box;

  &__text {
    font-size: 32rpx;
    font-weight: 600;
    color: #172033;
  }
}

.home-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 70vh;

  .loading-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #e5e5e5;
    border-top-color: #2979ff;
    border-radius: 50%;
    animation: home-spin 0.8s linear infinite;
  }

  .loading-text {
    margin-top: 12px;
    font-size: 13px;
    color: #999;
  }
}

@keyframes home-spin {
  to { transform: rotate(360deg); }
}

.header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: linear-gradient(135deg, #2979ff, #1e5fcc);

  &__content {
    display: flex;
    flex-direction: column;
  }

  &__info {
    padding: 20rpx 32rpx 28rpx;
  }

  &__title {
    display: block;
    font-size: 40rpx;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 6rpx;
  }

  &__subtitle {
    display: block;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.8);
  }
}

.home-body {
  padding: 0 $page-padding $page-padding;
}

.banner-section {
  margin-bottom: 24rpx;
  margin-top: 24rpx;

  .banner-swiper {
    height: 300rpx;
    border-radius: 16rpx;
    overflow: hidden;
  }

  .banner-image {
    width: 100%;
    height: 100%;
    border-radius: 16rpx;
  }
}

.grid-section {
  margin-bottom: 24rpx;

  .grid-card {
    display: flex;
    background: #ffffff;
    border-radius: 16rpx;
    padding: 32rpx 16rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
  }

  .grid-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .grid-icon {
    width: 88rpx;
    height: 88rpx;
    border-radius: 24rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16rpx;

    text {
      font-size: 40rpx;
      color: #ffffff;
    }
  }

  .icon-bg-blue { background: linear-gradient(135deg, #2979ff, #5b9bff); }
  .icon-bg-green { background: linear-gradient(135deg, #19be6b, #4dd893); }
  .icon-bg-orange { background: linear-gradient(135deg, #ff9900, #ffb74d); }
  .icon-bg-purple { background: linear-gradient(135deg, #7c4dff, #a98bff); }

  .grid-text {
    font-size: 24rpx;
    color: $text-color;
  }
}

.article-section {
  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20rpx;
  }

  .section-title {
    font-size: 32rpx;
    font-weight: 600;
    color: $text-color;
  }

  .section-more {
    font-size: 26rpx;
    color: $text-color-secondary;
  }
}

.article-list {
  .article-item {
    display: flex;
    align-items: center;
    background: #ffffff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 16rpx;
    box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.04);
  }

  .article-info {
    flex: 1;
    min-width: 0;
    margin-left: 20rpx;
  }

  .article-title {
    display: block;
    font-size: 28rpx;
    font-weight: 500;
    color: $text-color;
    margin-bottom: 16rpx;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.5;
  }

  .article-meta {
    display: flex;
    align-items: center;
    gap: 16rpx;
  }

  .article-category {
    font-size: 22rpx;
    color: $primary-color;
    background: rgba($primary-color, 0.1);
    padding: 4rpx 14rpx;
    border-radius: 8rpx;
  }

  .article-date {
    font-size: 22rpx;
    color: $text-color-secondary;
  }

  .article-cover {
    width: 160rpx;
    height: 110rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
  }
}

.article-empty {
  text-align: center;
  padding: 60rpx 0;

  &-text {
    font-size: 26rpx;
    color: $text-color-secondary;
  }
}
</style>
