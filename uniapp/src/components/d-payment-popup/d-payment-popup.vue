<template>
  <u-popup :show="visible" mode="bottom" :safeAreaInsetBottom="true"
    :customStyle="{ borderRadius: '24rpx 24rpx 0 0' }" @close="handlePopupClose">
    <view class="d-payment-popup">
      <view class="d-payment-popup__header">
        <text class="d-payment-popup__title">选择支付方式</text>
        <view class="d-payment-popup__amount">
          <text class="d-payment-popup__symbol">¥</text>
          <text class="d-payment-popup__price">{{ displayAmount }}</text>
        </view>
      </view>

      <view class="d-payment-popup__methods">
        <view v-for="opt in channelList" :key="opt.value" class="d-payment-popup__method"
          :class="{ 'is-active': selected === opt.value }" @tap="selected = opt.value">
          <view class="d-payment-popup__method-icon" :class="`d-payment-popup__method-icon--${opt.value}`">
            <text :class="CHANNEL_ICONS[opt.value]" style="font-size: 40rpx; color: #ffffff" />
          </view>
          <text class="d-payment-popup__method-name">{{ opt.label }}</text>
          <view :class="selected === opt.value ? 'i-ri-checkbox-circle-fill' : 'i-ri-checkbox-blank-circle-line'"
            :style="{ fontSize: '40rpx', color: selected === opt.value ? CHANNEL_COLORS[opt.value] : '#cccccc' }" />
        </view>
      </view>

      <view class="d-payment-popup__footer">
        <u-button type="primary" block :loading="loading" :disabled="loading" @click="handlePay">
          确认支付
        </u-button>
      </view>
    </view>
  </u-popup>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  buildChannelOptions,
  isMpWeixin,
  type PayChannel,
  type PayChannelOption,
} from '@/hooks/useOrderPayment'

const props = withDefaults(defineProps<{
  modelValue: boolean
  /** 金额（分）。与 amountText 二选一，amountText 优先（兼容 balance.vue 旧用法） */
  amount?: number
  /** 金额（元字符串），插件订单金额格式；传入时优先展示 */
  amountText?: string
  loading?: boolean
  /** 渠道候选；缺省=平台感知的 微信/支付宝（MP-WEIXIN 仅微信）。插件传 hook 的 channelOptions 以获得 dev mock */
  channels?: PayChannelOption[]
}>(), {
  amount: 0,
  amountText: '',
  loading: false,
  channels: undefined,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  pay: [channel: PayChannel]
  /**
   * 仅用户主动关闭弹层时触发（点击蒙层等 u-popup 内部 close 场景）。
   * 父组件程序化置 modelValue=false（如支付成功后自行收起）不会触发本事件，
   * 用于承接「用户主动放弃支付」的引导跳转等业务逻辑；不监听则零影响（如 balance.vue）。
   */
  close: []
}>()

const CHANNEL_ICONS: Record<PayChannel, string> = {
  wechat: 'i-ri-wechat-pay-fill',
  alipay: 'i-ri-alipay-fill',
  mock: 'i-ri-bug-line',
}
const CHANNEL_COLORS: Record<PayChannel, string> = {
  wechat: '#07c160',
  alipay: '#1677ff',
  mock: '#909399',
}

const channelList = computed<PayChannelOption[]>(
  () => props.channels ?? buildChannelOptions(false, { hideAlipay: isMpWeixin() })
)

const selected = ref<PayChannel>(channelList.value[0]?.value ?? 'wechat')

// channels 变化（或异步就绪）时，选中项不在列表内则重置为首项
watch(channelList, (list) => {
  if (!list.some((o) => o.value === selected.value)) {
    selected.value = list[0]?.value ?? 'wechat'
  }
})

const visible = computed({
  get: () => props.modelValue,
  set: (val: boolean) => emit('update:modelValue', val),
})

const displayAmount = computed(() =>
  props.amountText !== '' ? props.amountText : (props.amount / 100).toFixed(2)
)

function handlePay() {
  emit('pay', selected.value)
}

function handlePopupClose() {
  visible.value = false
  emit('close')
}
</script>

<style lang="scss" scoped>
.d-payment-popup {
  padding: 40rpx 32rpx;

  &__header {
    text-align: center;
    margin-bottom: 48rpx;
  }

  &__title {
    display: block;
    font-size: 32rpx;
    font-weight: 600;
    color: #333333;
    margin-bottom: 20rpx;
  }

  &__symbol {
    font-size: 32rpx;
    font-weight: 600;
    color: #fa3534;
  }

  &__price {
    font-size: 56rpx;
    font-weight: 700;
    color: #fa3534;
  }

  &__methods {
    margin-bottom: 48rpx;
  }

  &__method {
    display: flex;
    align-items: center;
    padding: 28rpx 24rpx;
    background: #f8f8f8;
    border-radius: 16rpx;
    margin-bottom: 20rpx;
    border: 2rpx solid transparent;
    transition: all 0.2s;

    &.is-active {
      background: #ffffff;
      border-color: #2979ff;
      box-shadow: 0 4rpx 16rpx rgba(41, 121, 255, 0.1);
    }
  }

  &__method-icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 16rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20rpx;

    &--wechat {
      background: #07c160;
    }

    &--alipay {
      background: #1677ff;
    }

    &--mock {
      background: #909399;
    }
  }

  &__method-name {
    flex: 1;
    font-size: 30rpx;
    color: #333333;
    font-weight: 500;
  }

  &__footer {
    padding-bottom: 20rpx;
  }
}
</style>
