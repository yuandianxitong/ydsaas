<template>
  <view class="register-page">
    <!-- 主题色头部 -->
    <view class="register-header" :style="{ paddingTop: statusBarHeight + 'px' }">
      <view class="register-header__bubble register-header__bubble--1" />
      <view class="register-header__bubble register-header__bubble--2" />
      <view class="register-header__back" :style="{ top: `calc(${statusBarHeight}px + 24rpx)` }" @tap="goLogin">
        <view class="i-ri-arrow-left-s-line" style="font-size: 40rpx; color: #ffffff" />
      </view>
      <view class="page-header">
        <text class="page-title">创建账号</text>
        <text class="page-subtitle">注册后即可使用全部功能</text>
      </view>
    </view>

    <!-- 注册表单 -->
    <view class="form-card">
      <!-- 手机号 -->
      <view class="input-group">
        <view class="i-ri-smartphone-line input-prefix" style="font-size: 36rpx; color: #909399" />
        <input
          v-model="form.mobile"
          type="number"
          maxlength="11"
          placeholder="请输入手机号"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
      </view>

      <!-- 短信验证码 -->
      <view class="input-group">
        <view class="i-ri-shield-check-line input-prefix" style="font-size: 36rpx; color: #909399" />
        <input
          v-model="form.code"
          type="number"
          maxlength="6"
          placeholder="请输入验证码"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
        <view class="sms-btn" :class="{ disabled: countdown > 0 }" @tap="handleSendCode">
          {{ countdown > 0 ? `${countdown}s` : '获取验证码' }}
        </view>
      </view>

      <!-- 密码 -->
      <view class="input-group">
        <view class="i-ri-lock-line input-prefix" style="font-size: 36rpx; color: #909399" />
        <input
          v-model="form.password"
          :password="!showPwd"
          placeholder="请设置密码（6-20位）"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
        <view class="input-suffix" @tap="showPwd = !showPwd">
          <text class="pwd-toggle-text">{{ showPwd ? '隐藏' : '显示' }}</text>
        </view>
      </view>

      <!-- 确认密码 -->
      <view class="input-group">
        <view class="i-ri-lock-line input-prefix" style="font-size: 36rpx; color: #909399" />
        <input
          v-model="form.confirmPassword"
          :password="!showConfirmPwd"
          placeholder="请再次输入密码"
          class="uni-input"
          placeholder-class="input-placeholder"
        />
        <view class="input-suffix" @tap="showConfirmPwd = !showConfirmPwd">
          <text class="pwd-toggle-text">{{ showConfirmPwd ? '隐藏' : '显示' }}</text>
        </view>
      </view>

      <!-- 协议 -->
      <d-agreement-check v-model="agreed" />

      <!-- 注册按钮 -->
      <view class="submit-btn" :class="{ loading }" @tap="!loading && handleRegister()">
        <text class="submit-btn__text">{{ loading ? '注册中...' : '立即注册' }}</text>
      </view>
    </view>

    <!-- 去登录 -->
    <view class="footer-link">
      <text class="footer-link__text">已有账号？</text>
      <text class="footer-link__action" @tap="goLogin">立即登录</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { authApi } from '@/api/auth'
import { isPassword, isMobile } from '@/utils/validate'
import { useUserStore } from '@/store/user.store'
import { useCountdown } from '@/hooks/useCountdown'

const userStore = useUserStore()

const statusBarHeight = ref(0)
try {
  statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 0
} catch {
  statusBarHeight.value = 44
}

const loading = ref(false)
const agreed = ref(false)
const showPwd = ref(false)
const showConfirmPwd = ref(false)
const { countdown, start: startCountdown } = useCountdown(60)

const form = reactive({
  mobile: '',
  code: '',
  password: '',
  confirmPassword: '',
})

async function handleSendCode() {
  if (countdown.value > 0) return
  if (!isMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  try {
    await authApi.sendSmsCode({ mobile: form.mobile, scene: 'register' })
    uni.showToast({ title: '验证码已发送', icon: 'none' })
    startCountdown()
  } catch {
    // 错误已由请求拦截器处理
  }
}

async function handleRegister() {
  if (!isMobile(form.mobile)) {
    uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
    return
  }
  if (!form.code) {
    uni.showToast({ title: '请输入验证码', icon: 'none' })
    return
  }
  if (!isPassword(form.password)) {
    uni.showToast({ title: '密码长度6-20位', icon: 'none' })
    return
  }
  if (form.password !== form.confirmPassword) {
    uni.showToast({ title: '两次密码输入不一致', icon: 'none' })
    return
  }
  if (!agreed.value) {
    uni.showToast({ title: '请先同意用户协议', icon: 'none' })
    return
  }

  loading.value = true
  try {
    // 通过 store 封装方法注册，store 内部统一处理 token、userInfo 和 H5 OAuth 绑定
    await userStore.register({
      mobile: form.mobile,
      code: form.code,
      password: form.password,
      password_confirmation: form.confirmPassword,
    })
    uni.showToast({ title: '注册成功' })
    setTimeout(() => {
      uni.reLaunch({ url: '/pages/index/index' })
    }, 1500)
  } finally {
    loading.value = false
  }
}

function goLogin() {
  uni.navigateBack()
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';

.register-page {
  min-height: 100vh;
  background-color: #f7f8fa;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
}

.register-header {
  background: var(--theme-color, #{$primary-color});
  border-radius: 0 0 60rpx 60rpx;
  position: relative;
  overflow: hidden;
  padding-bottom: 80rpx;

  &__bubble {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);

    &--1 {
      width: 260rpx;
      height: 260rpx;
      top: -60rpx;
      right: -40rpx;
    }

    &--2 {
      width: 160rpx;
      height: 160rpx;
      bottom: 20rpx;
      left: -30rpx;
      background: rgba(255, 255, 255, 0.05);
    }
  }

  &__back {
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
}

.page-header {
  /* 顶部留白须越过返回钮行（top 24rpx + 高 72rpx = 96rpx），否则标题与返回钮挤在一行 */
  padding: 140rpx 40rpx 0;
  position: relative;
  z-index: 1;

  .page-title {
    display: block;
    font-size: 48rpx;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 12rpx;
  }

  .page-subtitle {
    font-size: 28rpx;
    color: rgba(255, 255, 255, 0.75);
  }
}

.form-card {
  background-color: #ffffff;
  border-radius: 24rpx;
  padding: 40rpx 32rpx;
  box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.06);
  margin: -30rpx 32rpx 0;
  position: relative;
  z-index: 1;
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

.submit-btn {
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
