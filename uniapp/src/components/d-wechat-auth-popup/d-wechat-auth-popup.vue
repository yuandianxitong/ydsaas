<template>
  <u-popup :show="visible" mode="bottom" :safeAreaInsetBottom="true"
    :customStyle="{ borderRadius: '24rpx 24rpx 0 0' }" @close="handleClose">
    <view class="wx-auth">
      <view class="wx-auth__header">
        <text class="wx-auth__title">获取您的昵称、头像{{ showPhone ? '、手机号' : '' }}</text>
        <text class="wx-auth__tips">用于完善个人资料，向您提供更有辨识度的个人中心</text>
      </view>

      <!-- #ifdef MP-WEIXIN -->
      <view class="wx-auth__row">
        <text class="wx-auth__label">头像<text class="wx-auth__required">*</text></text>
        <button class="wx-auth__avatar-btn" open-type="chooseAvatar" @chooseavatar="onChooseAvatar">
          <image v-if="avatarPreview || props.defaultAvatar" class="wx-auth__avatar"
            :src="avatarPreview || props.defaultAvatar" mode="aspectFill" />
          <view v-else class="wx-auth__avatar wx-auth__avatar--placeholder">
            <text class="i-ri-user-fill" />
          </view>
          <text class="wx-auth__arrow">›</text>
        </button>
      </view>
      <view class="wx-auth__row">
        <text class="wx-auth__label">昵称</text>
        <input class="wx-auth__input" type="nickname" v-model="nickname" placeholder="请输入昵称"
          maxlength="30" @blur="onNicknameBlur" />
      </view>
      <view v-if="showPhone" class="wx-auth__row">
        <text class="wx-auth__label">手机号</text>
        <button v-if="!phoneDisplay" class="wx-auth__phone-btn" open-type="getPhoneNumber"
          @getphonenumber="onGetPhone">点击授权手机号</button>
        <text v-else class="wx-auth__phone-done">{{ phoneDisplay }}</text>
      </view>
      <!-- #endif -->

      <view class="wx-auth__footer">
        <u-button type="primary" block :loading="loading" :disabled="!submitEnabled" @click="handleSubmit">
          保存并登录
        </u-button>
      </view>
    </view>
  </u-popup>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { uploadApi } from '@/api/upload'
import { canSubmit, buildSubmitPayload } from './helpers'

const props = withDefaults(defineProps<{
  modelValue: boolean
  showPhone?: boolean
  phoneDisplay?: string
  defaultAvatar?: string
  defaultNickname?: string
  loading?: boolean
}>(), { showPhone: false, phoneDisplay: '', defaultAvatar: '', defaultNickname: '', loading: false })

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: { nickname: string; avatar?: string }]
  'phone-auth': [code: string]
  close: []
}>()

const visible = computed({ get: () => props.modelValue, set: (v: boolean) => emit('update:modelValue', v) })

// defaultNickname 为注册默认串「微信用户」时不回显，留 placeholder 引导真实昵称
const nickname = ref(props.defaultNickname === '微信用户' ? '' : props.defaultNickname)
const avatarPath = ref('')       // 上传成功后的相对 path（提交用）
const avatarPreview = ref('')    // 本地临时路径（预览用）
const uploading = ref(false)     // 头像上传在途：期间禁止提交，避免保存丢头像

watch(() => props.modelValue, (open) => {
  if (open) {
    nickname.value = props.defaultNickname === '微信用户' ? '' : props.defaultNickname
    avatarPath.value = ''
    avatarPreview.value = ''
  }
})

const submitEnabled = computed(() =>
  canSubmit(nickname.value, avatarPath.value, props.showPhone, props.phoneDisplay, props.loading) && !uploading.value)

async function onChooseAvatar(e: any): Promise<void> {
  // need_bindphone 场景下手机号未授权前 getToken 为空：上传接口挂 api_auth，未授权直接上传必 401，
  // 故在此门控，引导用户先完成手机号授权换正式 token
  if (props.showPhone && !props.phoneDisplay) {
    uni.showToast({ title: '请先授权手机号', icon: 'none' })
    return
  }
  const tempPath = String(e?.detail?.avatarUrl ?? '')
  if (!tempPath) return
  avatarPreview.value = tempPath
  uploading.value = true
  try {
    const result = await uploadApi.uploadImage(tempPath)
    avatarPath.value = result.path || result.url
  } catch {
    avatarPreview.value = ''
    avatarPath.value = ''
    uni.showToast({ title: '头像上传失败，请重试', icon: 'none' })
  } finally {
    uploading.value = false
  }
}

function onNicknameBlur(e: any): void {
  const v = String(e?.detail?.value ?? '')
  if (v) nickname.value = v
}

function onGetPhone(e: any): void {
  if (e?.detail?.errMsg === 'getPhoneNumber:ok' && e.detail.code) {
    emit('phone-auth', String(e.detail.code))
  } else {
    uni.showToast({ title: '取消授权将无法完成登录', icon: 'none' })
  }
}

function handleSubmit(): void {
  emit('submit', buildSubmitPayload(nickname.value, avatarPath.value))
}

function handleClose(): void {
  visible.value = false
  emit('close')
}
</script>

<style lang="scss" scoped>
@import '@/styles/variables.scss';
.wx-auth {
  padding: 40rpx 32rpx calc(20rpx + env(safe-area-inset-bottom));
  &__header { text-align: left; padding-bottom: 24rpx; border-bottom: 1rpx solid $border-color; }
  &__title { display: block; font-size: 32rpx; font-weight: 600; color: $text-color; }
  &__tips { display: block; margin-top: 8rpx; font-size: 24rpx; color: $text-color-secondary; }
  &__row { display: flex; align-items: center; padding: 24rpx 0; border-bottom: 1rpx solid $border-color; }
  &__label { width: 120rpx; font-size: 28rpx; color: $text-color; }
  &__required { color: $primary-color; margin-left: 4rpx; }
  &__avatar-btn {
    flex: 1; display: flex; align-items: center; justify-content: space-between;
    background: transparent; padding: 0; margin: 0; border: none; line-height: 1;
    &::after { border: none; }
  }
  &__avatar {
    width: 96rpx; height: 96rpx; border-radius: 12rpx; background: $bg-color;
    &--placeholder {
      display: flex; align-items: center; justify-content: center;
      text { font-size: 56rpx; color: #c0c4cc; }
    }
  }
  &__arrow { font-size: 32rpx; color: #cccccc; }
  &__input { flex: 1; height: 72rpx; font-size: 28rpx; }
  &__phone-btn {
    flex: 1; text-align: left; background: transparent; padding: 0; margin: 0; border: none;
    font-size: 28rpx; color: $primary-color; line-height: 72rpx; height: 72rpx;
    &::after { border: none; }
  }
  &__phone-done { flex: 1; font-size: 28rpx; color: $text-color; }
  &__footer { margin-top: 40rpx; }
}
</style>
