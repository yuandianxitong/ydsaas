<template>
  <view class="login-page">
    <!-- 主题色头部 -->
    <view class="login-header" :style="{ paddingTop: statusBarHeight + 'px' }">
      <view class="login-back" :style="{ top: `calc(${statusBarHeight}px + 24rpx)` }" @tap="handleBack">
        <view class="i-ri-arrow-left-s-line" style="font-size: 40rpx; color: #ffffff" />
      </view>
      <view class="login-header__bubble login-header__bubble--1" />
      <view class="login-header__bubble login-header__bubble--2" />
      <view class="login-header__bubble login-header__bubble--3" />
      <view class="logo-area">
        <image class="logo" :src="logoUrl || '/static/logo.png'" mode="aspectFit" />
        <text class="app-name">{{ siteName || '元点SaaS' }}</text>
        <text class="app-slogan">欢迎回来</text>
      </view>
    </view>

    <view class="content-wrap">
      <!-- 登录表单卡片 -->
      <view class="form-card">
        <!-- Tab 切换 -->
        <view class="tab-bar">
          <view
            class="tab-item"
            :class="{ active: loginType === 'password' }"
            @tap="loginType = 'password'"
          >
            密码登录
          </view>
          <view
            class="tab-item"
            :class="{ active: loginType === 'sms' }"
            @tap="loginType = 'sms'"
          >
            验证码登录
          </view>
        </view>

        <!-- 手机号输入 -->
        <view class="input-group">
          <view class="i-ri-smartphone-line input-prefix" style="font-size: 36rpx; color: #909399" />
          <input
            v-model="mobile"
            type="number"
            maxlength="11"
            placeholder="请输入手机号"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
        </view>

        <!-- 密码输入 -->
        <view v-if="loginType === 'password'" class="input-group">
          <view class="i-ri-lock-line input-prefix" style="font-size: 36rpx; color: #909399" />
          <input
            v-model="password"
            :password="!showPwd"
            placeholder="请输入密码（6-20位）"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
          <view class="input-suffix" @tap="showPwd = !showPwd">
            <text class="pwd-toggle-text">{{ showPwd ? '隐藏' : '显示' }}</text>
          </view>
        </view>

        <!-- 验证码输入 -->
        <view v-else class="input-group">
          <view class="i-ri-shield-check-line input-prefix" style="font-size: 36rpx; color: #909399" />
          <input
            v-model="smsCode"
            type="number"
            maxlength="6"
            placeholder="请输入验证码"
            class="uni-input"
            placeholder-class="input-placeholder"
          />
          <view
            class="sms-btn"
            :class="{ disabled: countdown > 0 }"
            @tap="handleSendCode"
          >
            {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
          </view>
        </view>

        <!-- 协议 -->
        <d-agreement-check v-model="agreed" />

        <!-- 登录按钮 -->
        <view class="login-btn" :class="{ loading }" @tap="!loading && handleLogin()">
          <text class="login-btn__text">{{ loading ? '登录中...' : '登录' }}</text>
        </view>

        <!-- #ifdef MP-WEIXIN -->
        <view class="wechat-quick-section">
          <view class="divider-line">
            <view class="line" /><text class="divider-text">其他方式登录</text><view class="line" />
          </view>

          <view
            class="wechat-login-icon"
            :class="{ loading: wechatQuickLoading }"
            @tap="!wechatQuickLoading && handleWechatQuickLogin()"
          >
            <view class="i-ri-wechat-fill wechat-icon-inner" />
          </view>
        </view>
        <!-- #endif -->
      </view>

      <!-- 底部链接 -->
      <view class="footer-link">
        <text class="footer-link__text">还没有账号？</text>
        <text class="footer-link__action" @tap="goRegister">立即注册</text>
      </view>
    </view>

    <d-wechat-auth-popup
      v-model="showAuthPopup"
      :show-phone="authPopupShowPhone"
      :phone-display="authPhoneDisplay"
      :default-nickname="profileDefaults.nickname"
      :default-avatar="profileDefaults.avatar ? appStore.getImageUrl(profileDefaults.avatar) : ''"
      :loading="authSubmitting"
      @phone-auth="handleAuthPhoneCode"
      @submit="handleAuthSubmit"
      @close="handleAuthClose"
    />
  </view>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { useLogin } from '../composables/useLogin'
import { useAppStore } from '@/store/app.store'

const appStore = useAppStore()

const {
  loading, loginType, countdown,
  loginByPassword, loginBySms, sendCode,
  wechatQuickLoading, loginByWechatQuick,
  showAuthPopup, authPopupShowPhone, authPhoneDisplay, authSubmitting, profileDefaults,
  handleAuthPhoneCode, handleAuthSubmit, handleAuthClose,
} = useLogin()

const mobile = ref('')
const password = ref('')
const smsCode = ref('')
const agreed = ref(false)
const showPwd = ref(false)

// 获取状态栏高度
const statusBarHeight = ref(0)
const systemInfo = uni.getSystemInfoSync()
statusBarHeight.value = systemInfo.statusBarHeight || 0

const siteName = computed(() => appStore.config.site_name || '')
const logoUrl = computed(() => {
  const logo = appStore.config.site_logo
  return logo ? appStore.getImageUrl(logo) : ''
})

onShow(() => {
  appStore.getConfig()
})

function checkAgreement(): boolean {
  if (!agreed.value) {
    uni.showToast({ title: '请先同意用户协议', icon: 'none' })
    return false
  }
  return true
}

async function handleLogin() {
  if (!checkAgreement()) return
  if (loginType.value === 'password') {
    await loginByPassword(mobile.value, password.value)
  } else {
    await loginBySms(mobile.value, smsCode.value)
  }
}

async function handleSendCode() {
  await sendCode(mobile.value)
}

function goRegister() {
  uni.navigateTo({ url: '/modules/login/pages/register' })
}

function handleBack() {
  const pages = getCurrentPages()
  if (pages.length > 1) {
    uni.navigateBack()
    return
  }
  uni.switchTab({ url: '/pages/index/index' })
}

async function handleWechatQuickLogin() {
  if (!checkAgreement()) return
  await loginByWechatQuick()
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.login-page {
  min-height: 100vh;
  background-color: #f7f8fa;
  display: flex;
  flex-direction: column;
}

.login-header {
  background: var(--theme-color, #{$primary-color});
  border-radius: 0 0 60rpx 60rpx;
  position: relative;
  overflow: hidden;

  &__bubble {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);

    &--1 {
      width: 300rpx;
      height: 300rpx;
      top: -80rpx;
      right: -60rpx;
    }

    &--2 {
      width: 180rpx;
      height: 180rpx;
      top: 100rpx;
      left: -40rpx;
      background: rgba(255, 255, 255, 0.06);
    }

    &--3 {
      width: 120rpx;
      height: 120rpx;
      bottom: 40rpx;
      right: 80rpx;
      background: rgba(255, 255, 255, 0.05);
    }
  }
}

.login-back {
  position: absolute;
  left: 24rpx; /* 全端左上角：MP 右上被系统胶囊占据（用户裁决） */
  z-index: 2;
  width: 72rpx;
  height: 72rpx;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.16);
}

.logo-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60rpx 0 80rpx;
  position: relative;
  z-index: 1;

  .logo {
    width: 120rpx;
    height: 120rpx;
    border-radius: 28rpx;
    background-color: rgba(255, 255, 255, 0.2);
    box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.1);
  }

  .app-name {
    font-size: 36rpx;
    font-weight: 700;
    color: #ffffff;
    margin-top: 24rpx;
  }

  .app-slogan {
    font-size: 26rpx;
    color: rgba(255, 255, 255, 0.75);
    margin-top: 8rpx;
  }
}

.content-wrap {
  position: relative;
  z-index: 1;
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0 32rpx;
  margin-top: -30rpx;
  box-sizing: border-box;
}

.form-card {
  background-color: #ffffff;
  border-radius: 24rpx;
  padding: 40rpx 32rpx;
  box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.06);
}

.tab-bar {
  display: flex;
  justify-content: center;
  gap: 80rpx;
  margin-bottom: 60rpx;
  border-bottom: none;

  .tab-item {
    padding: 16rpx 0;
    font-size: 30rpx;
    color: $text-color-secondary;
    position: relative;
    font-weight: 500;
    transition: all 0.3s;

    &.active {
      color: $text-color;
      font-weight: 600;
      font-size: 36rpx;

      &::after {
        content: '';
        position: absolute;
        bottom: -10rpx;
        left: 50%;
        transform: translateX(-50%);
        width: 40rpx;
        height: 6rpx;
        background: var(--theme-color, #{$primary-color});
        border-radius: 3rpx;
      }
    }
  }
}

.input-group {
  display: flex;
  align-items: center;
  margin-bottom: 28rpx;
  border: none;
  background-color: #f5f7fa;
  border-radius: 16rpx;
  padding: 0 28rpx;
  height: 96rpx;
  transition: all 0.2s;

  &:focus-within {
    box-shadow: 0 0 0 1px var(--theme-color, #{$primary-color});
    background-color: #fff;
  }

  .input-prefix {
    flex-shrink: 0;
    margin-right: 16rpx;
  }

  .uni-input {
    flex: 1;
    height: 100%;
    font-size: 30rpx;
    color: $text-color;
    background: transparent;
  }

  .input-suffix {
    padding-left: 20rpx;
  }

  .pwd-toggle-text {
    font-size: 28rpx;
    color: $text-color-secondary;
  }

  .sms-btn {
    font-size: 28rpx;
    color: var(--theme-color, #{$primary-color});
    font-weight: 500;
    white-space: nowrap;

    &.disabled {
      color: $text-color-secondary;
    }
  }
}

.input-placeholder {
  color: #c0c4cc;
  font-size: 30rpx;
}

.login-btn {
  margin-top: 48rpx;
  height: 100rpx;
  background-color: var(--theme-color, #{$primary-color});
  border-radius: 50rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 10rpx 20rpx -10rpx var(--theme-color, #{$primary-color});
  transition: all 0.3s ease;

  &:active {
    transform: translateY(2rpx);
    box-shadow: 0 5rpx 10rpx -5rpx var(--theme-color, #{$primary-color});
  }

  &.loading {
    opacity: 0.7;
  }

  &__text {
    font-size: 32rpx;
    font-weight: 600;
    color: #ffffff;
    letter-spacing: 2rpx;
  }
}

.wechat-quick-section {
  margin-top: 60rpx;
}

.divider-line {
  display: flex;
  align-items: center;
  margin-bottom: 40rpx;

  .line {
    flex: 1;
    height: 1rpx;
    background: #e4e7ed;
  }

  .divider-text {
    font-size: 24rpx;
    color: #909399;
    padding: 0 30rpx;
  }
}

.wechat-login-icon {
  width: 96rpx;
  height: 96rpx;
  border-radius: 50%;
  background: #07c160;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
  box-shadow: 0 4rpx 16rpx rgba(7, 193, 96, 0.3);

  .wechat-icon-inner {
    width: 48rpx;
    height: 48rpx;
    color: #ffffff;
  }

  &.loading {
    opacity: 0.6;
  }
}

.footer-link {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: auto;
  padding: 40rpx 0;
  padding-bottom: calc(60rpx + env(safe-area-inset-bottom));

  &__text {
    font-size: 28rpx;
    color: $text-color-secondary;
  }

  &__action {
    font-size: 28rpx;
    color: var(--theme-color, #{$primary-color});
    margin-left: 12rpx;
    font-weight: 500;
  }
}
</style>
