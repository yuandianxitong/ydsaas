<template>
  <view class="my-page">
    <DiyRenderer
      v-if="hasRenderableDecoration(memberPage)"
      :components="memberPage!.components"
      :page-settings="memberPage!.page_settings"
    />
    <template v-else>
      <!-- User Card with gradient background -->
      <view id="my-user-header" class="user-header">
        <view class="status-bar" :style="{ height: statusBarHeight + 'px' }" />
        <view class="user-card" @tap="goUserAction">
          <view class="avatar-wrap">
            <image
              v-if="userStore.isLoggedIn && userStore.avatar"
              class="avatar"
              :src="avatarUrl"
              mode="aspectFill"
            />
            <view v-else class="default-avatar">
              <text class="i-ri-user-fill" />
            </view>
          </view>
          <view v-if="userStore.isLoggedIn" class="user-info">
            <text class="user-name">{{ userStore.nickname || '未设置昵称' }}</text>
            <text class="user-phone">{{ maskMobile(userStore.userInfo?.mobile) }}</text>
          </view>
          <view v-else class="user-info">
            <text class="user-name">点击登录</text>
            <text class="user-phone">登录后享受更多功能</text>
          </view>
          <view class="i-ri-arrow-right-s-line" style="font-size: 36rpx; color: rgba(255,255,255,0.8)" />
        </view>

        <!-- Balance & Points always visible in header area -->
        <view class="header-assets">
          <view class="header-assets-item" @tap="goAuthPage('/modules/user/pages/balance')">
            <text class="header-assets-value">{{ fallbackBalance }}</text>
            <text class="header-assets-label">余额</text>
          </view>
          <view class="header-assets-divider" />
          <view class="header-assets-item" @tap="goAuthPage('/modules/user/pages/points')">
            <text class="header-assets-value">{{ fallbackPoints }}</text>
            <text class="header-assets-label">积分</text>
          </view>
        </view>
      </view>

      <!-- Menu Groups -->
      <view class="menu-body" :style="{ paddingTop: headerHeight + 'px' }">
        <!-- Group 1: Profile -->
        <view class="menu-card">
          <u-cell-group>
            <u-cell title="个人资料" isLink @click="goAuthPage('/modules/user/pages/edit-profile')">
              <template #icon>
                <text class="i-ri-user-3-line cell-icon" style="color: #2979ff" />
              </template>
            </u-cell>
            <u-cell title="修改密码" isLink @click="goAuthPage('/modules/user/pages/change-password')">
              <template #icon>
                <text class="i-ri-shield-check-line cell-icon" style="color: #19be6b" />
              </template>
            </u-cell>
            <u-cell title="关于我们" isLink @click="goPage('/modules/about/pages/about')">
              <template #icon>
                <text class="i-ri-question-line cell-icon" style="color: #909399" />
              </template>
            </u-cell>
            <u-cell title="设置" isLink @click="goPage('/modules/user/pages/settings')">
              <template #icon>
                <text class="i-ri-settings-3-line cell-icon" style="color: #fa3534" />
              </template>
            </u-cell>
          </u-cell-group>
        </view>
      </view>
    </template>
    <AppTabBar current="pages/my/index" />
  </view>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import AppTabBar from '@/components/tabbar/AppTabBar.vue'
import DiyRenderer from '@/components/diy/DiyRenderer.vue'
import { onShow, onShareAppMessage } from '@dcloudio/uni-app'
import { useUserStore } from '@/store/user.store'
import { useAppStore } from '@/store/app.store'
import { useMobileConfigStore } from '@/store/mobile-config.store'
import { messageApi } from '@/api/message'
import { mobileConfigApi } from '@/api/mobile-config'
import { getStatusBarHeight } from '@/utils/platform'
import pagesJson from '@/pages.json'
import { hasRenderableDecoration, type DiyPagePayload } from './memberDecoration'
import { collectStatKeys, provideMemberStats } from '@/hooks/useMemberStats'

const userStore = useUserStore()
const appStore = useAppStore()
const mobileConfigStore = useMobileConfigStore()
const { stats: memberStats, refreshKeys: refreshMemberStatKeys } = provideMemberStats()

const serviceType = computed(() => mobileConfigStore.serviceType)

// pages.json 由构建前钩子按租户权益生成：未授权 shop 插件时分包不存在，入口必须隐藏
const hasShop = (pagesJson.subPackages ?? []).some(
  (sp: { root: string }) => sp.root === 'modules/shop'
)

function contactService() {
  const type = mobileConfigStore.serviceType
  if (type === 'phone' && mobileConfigStore.servicePhone) {
    uni.makePhoneCall({ phoneNumber: mobileConfigStore.servicePhone, fail: () => {} })
    return
  }
  // #ifdef MP-WEIXIN
  if (type === 'wechat') {
    ;(uni as any).openCustomerServiceChat?.({ fail: () => uni.showToast({ title: '客服暂不可用', icon: 'none' }) })
    return
  }
  // #endif
  uni.showToast({ title: '请稍后再试', icon: 'none' })
}

onShareAppMessage(() => ({
  title: mobileConfigStore.shareTitle,
  imageUrl: mobileConfigStore.shareImage || undefined,
}))

const statusBarHeight = ref(getStatusBarHeight())
const headerHeight = ref(280) // 占位高度，mount 后通过实际测量覆盖
const unreadCount = ref(0)
const memberPage = ref<DiyPagePayload | null>(null)

const fallbackBalance = computed(() =>
  userStore.isLoggedIn ? String(memberStats.value['user.balance'] ?? '0.00') : '**'
)
const fallbackPoints = computed(() =>
  userStore.isLoggedIn ? String(memberStats.value['user.points'] ?? 0) : '**'
)

/**
 * 动态测量 user-header 实际高度
 *
 * 替代原先硬编码的 `statusBarHeight + 260`，260 是按默认屏幕估算的固定值，
 * 在折叠屏、异形屏以及不同字号设置下会出现偏差。改为 createSelectorQuery 取真实值。
 */
function measureHeaderHeight() {
  nextTick(() => {
    const query = uni.createSelectorQuery()
    query
      .select('#my-user-header')
      .boundingClientRect((rect: any) => {
        if (rect && rect.height) {
          headerHeight.value = Math.ceil(rect.height)
        }
      })
      .exec()
  })
}

const avatarUrl = computed(() => {
  if (userStore.isLoggedIn && userStore.avatar) {
    return appStore.getImageUrl(userStore.avatar)
  }
  return ''
})

function maskMobile(mobile?: string): string {
  if (!mobile || mobile.length < 11) return mobile || ''
  return mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
}

function goUserAction() {
  if (userStore.isLoggedIn) {
    uni.navigateTo({ url: '/modules/user/pages/edit-profile' })
  } else {
    uni.navigateTo({ url: '/modules/login/pages/login' })
  }
}

function goAuthPage(url: string) {
  if (!userStore.isLoggedIn) {
    uni.navigateTo({ url: '/modules/login/pages/login' })
    return
  }
  uni.navigateTo({ url })
}

function goPage(url: string) {
  uni.navigateTo({ url })
}

function goMessageTab() {
  uni.switchTab({ url: '/pages/message/index' })
}

function loadUnreadCount() {
  if (!userStore.isLoggedIn) {
    unreadCount.value = 0
    return
  }
  messageApi
    .getUnreadCount()
    .then((res) => {
      unreadCount.value = res.count || 0
    })
    .catch(() => {
      unreadCount.value = 0
    })
}

onShow(async () => {
  if (userStore.isLoggedIn && !userStore.userInfo) {
    userStore.getUserInfo().catch(() => {})
  }
  loadUnreadCount()
  // 头部高度可能随登录状态变化（登录前/后文案不同），每次显示都重新测量
  measureHeaderHeight()
  // member 装修数据：未发布/不存在时接口 404，静默回退静态布局
  try {
    memberPage.value = (await mobileConfigApi.getDiyPage('member')) as unknown as DiyPagePayload
  } catch {
    memberPage.value = null
  }
  if (hasRenderableDecoration(memberPage.value)) {
    // 存量发布树（如 user-info-card 无 assets 字段）收集不到内置键，余额/积分会退化为 0：
    // 内置键恒并入，装修树自身声明的键不受影响；组件收到未使用的键也会忽略
    refreshMemberStatKeys([
      ...collectStatKeys(memberPage.value!.components),
      'user.balance',
      'user.points',
    ])
  } else {
    refreshMemberStatKeys(['user.balance', 'user.points'])
  }
})
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.my-page {
  min-height: 100vh;
  background-color: $bg-color;
  padding-bottom: calc(50px + constant(safe-area-inset-bottom));
  padding-bottom: calc(50px + env(safe-area-inset-bottom));
}

.user-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: linear-gradient(135deg, #2979ff, #1e5fcc);
  padding-bottom: 32rpx;
}

.user-card {
  display: flex;
  align-items: center;
  padding: 48rpx 32rpx;

  .avatar-wrap {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 4rpx solid rgba(255, 255, 255, 0.3);

    .avatar {
      width: 100%;
      height: 100%;
    }

    .default-avatar {
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;

      text {
        font-size: 64rpx;
        color: rgba(255, 255, 255, 0.8);
      }
    }
  }

  .user-info {
    flex: 1;
    margin-left: 24rpx;

    .user-name {
      display: block;
      font-size: 36rpx;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 8rpx;
    }

    .user-phone {
      display: block;
      font-size: 26rpx;
      color: rgba(255, 255, 255, 0.8);
    }
  }
}

.header-assets {
  display: flex;
  align-items: center;
  padding: 24rpx 32rpx 0;

  .header-assets-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .header-assets-value {
    font-size: 40rpx;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 4rpx;
  }

  .header-assets-label {
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.7);
  }

  .header-assets-divider {
    width: 1rpx;
    height: 60rpx;
    background: rgba(255, 255, 255, 0.2);
    flex-shrink: 0;
  }
}

.menu-body {
  padding: 0 $page-padding $page-padding;
}

.menu-card {
  background: #ffffff;
  border-radius: 24rpx;
  overflow: hidden;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);

  .cell-icon {
    font-size: 40rpx;
    margin-right: 16rpx;
  }
}

.version-info {
  text-align: center;
  padding: 40rpx 0;

  .version-text {
    font-size: 24rpx;
    color: $text-color-secondary;
  }
}
</style>
