import { ref } from 'vue'
import { isWeixin, isWeixinBrowser, isApp } from '@/utils/platform'
import { tenantConfig } from '@/generated/tenant-config'

/**
 * 插件统一支付 hook。插件只需注入自己的 pay API，页面配 `<d-payment-popup>` 十行接通：
 *
 *   const { paying, channelOptions, pay } = useOrderPayment(
 *     (orderNo, params) => orderApi.pay(orderNo, params)
 *   )
 *   // <d-payment-popup v-model="showPay" :channels="channelOptions"
 *   //   :amount-text="amount" :loading="paying" @pay="onPick" />
 *   async function onPick(channel: PayChannel) {
 *     showPay = false
 *     const outcome = await pay(orderNo, channel, amount)  // 'success'|'cancel'|'fail'
 *     // 支付后收尾（跳转/刷新）由页面自理，本 hook 不越界
 *   }
 *
 * trade_type 派生对齐主工程 `uniapp/src/modules/payment/composables/usePayment.ts` 的
 * `getTradeType()`：微信环境（小程序或微信内 H5）走 jsapi，App 走 app，否则 h5。
 */

export type PayChannel = 'wechat' | 'alipay' | 'mock'

export interface PayChannelOption {
  value: PayChannel
  label: string
}

/**
 * 渠道候选列表：微信始终可选；支付宝在 `opts.hideAlipay` 为真时剔除（微信小程序端调不起
 * 支付宝，见 Global Constraints）；mock 仅 `isDev=true`（即 `import.meta.env.DEV`）时追加，
 * 用于本地/CI 冒烟联调，绝不能出现在生产构建里（后端 `notify/mock` 端点本身在
 * `APP_DEBUG=false` 时也会伪装成 404，双重保险）。
 */
export function buildChannelOptions(
  isDev: boolean,
  opts: { hideAlipay?: boolean } = {}
): PayChannelOption[] {
  const options: PayChannelOption[] = [{ value: 'wechat', label: '微信支付' }]
  if (!opts.hideAlipay) {
    options.push({ value: 'alipay', label: '支付宝' })
  }
  if (isDev) {
    options.push({ value: 'mock', label: 'Mock 支付（开发调试）' })
  }
  return options
}

/** trade_type 派生：mock 渠道固定传 'mock'（驱动 `MockPayDriver::create()` 忽略该值，仅透传） */
export function resolveTradeType(channel: PayChannel): string {
  if (channel === 'mock') return 'mock'
  if (isWeixin() || isWeixinBrowser()) return 'jsapi'
  if (isApp()) return 'app'
  return 'h5'
}

/** 支付参数消费动作（纯函数产出，零 uni 依赖，可测）：由 pay() 按此结果执行副作用 */
export type PaymentConsumeAction =
  | { kind: 'requestPayment'; provider: 'wxpay' | 'alipay'; params: Record<string, unknown> }
  | { kind: 'h5Redirect'; url: string }
  | { kind: 'native'; codeUrl: string }
  | { kind: 'mock'; outTradeNo: string }
  | { kind: 'unknown' }

/** 订单支付接口的返回结构：只关心 `payment_data` 容器，具体形状由后端支付驱动决定 */
export interface OrderPayResult {
  payment_data: { trade_type?: string; data?: Record<string, unknown> }
}

/**
 * 按 `payment_data`（`{trade_type, data}`，见各插件 `*PaymentService::createPayment()` 透传的
 * 支付驱动 `create()` 返回值）决定消费方式，形状对齐 `usePayment.ts`：
 * jsapi/app -> uni.requestPayment；h5 -> location.href；native -> 展示 code_url；
 * mock -> 调 `/api/payment/notify/mock`。
 */
export function resolvePaymentAction(
  channel: PayChannel,
  paymentData: OrderPayResult['payment_data'] | null | undefined
): PaymentConsumeAction {
  const tradeType = paymentData?.trade_type ?? ''
  const data = paymentData?.data ?? {}

  if (tradeType === 'mock') {
    return { kind: 'mock', outTradeNo: String(data.out_trade_no ?? '') }
  }
  if (tradeType === 'jsapi' || tradeType === 'app') {
    return {
      kind: 'requestPayment',
      provider: channel === 'wechat' ? 'wxpay' : 'alipay',
      params: data,
    }
  }
  if (tradeType === 'h5') {
    return { kind: 'h5Redirect', url: typeof data.h5_url === 'string' ? data.h5_url : '' }
  }
  if (tradeType === 'native') {
    return { kind: 'native', codeUrl: typeof data.code_url === 'string' ? data.code_url : '' }
  }
  return { kind: 'unknown' }
}

export type PayOutcome = 'success' | 'cancel' | 'fail'

/** 是否微信小程序端（MP 调不起支付宝，渠道候选须剔除 alipay） */
export function isMpWeixin(): boolean {
  let mp = false
  // #ifdef MP-WEIXIN
  mp = true
  // #endif
  return mp
}

/**
 * dev-only mock 支付回调触发：`PaymentController::mockNotify()` 返回的是裸文本
 * （`text/plain`，驱动 `successResponse()`/失败态都是纯字符串，非 `{code,message,data}` 信封），
 * 不能走 `@/utils/request` 的 `http` 封装（其成功判定读 `response.code === 200` 会误判失败），
 * 因此这里直接用 `uni.request` 且只看 HTTP 状态码。
 */
function notifyMock(outTradeNo: string, totalAmount: string): Promise<void> {
  let baseUrl = import.meta.env.VITE_APP_API_URL || ''
  // #ifdef H5
  baseUrl = ''
  // #endif
  const header: Record<string, string> = { 'Content-Type': 'application/json' }
  if (tenantConfig.tenantCode) {
    header['X-Tenant-Code'] = tenantConfig.tenantCode
  }

  return new Promise((resolve, reject) => {
    uni.request({
      url: `${baseUrl}/api/payment/notify/mock`,
      method: 'POST',
      data: { out_trade_no: outTradeNo, total_amount: totalAmount },
      header,
      success: (res: any) => {
        if (res.statusCode === 200) {
          resolve()
        } else {
          reject(new Error('mock 回调失败'))
        }
      },
      fail: (err: any) => reject(err),
    })
  })
}

export function useOrderPayment(
  payFn: (orderNo: string, params: { channel: PayChannel; trade_type: string }) => Promise<OrderPayResult>
) {
  const paying = ref(false)
  const channelOptions = buildChannelOptions(import.meta.env.DEV, { hideAlipay: isMpWeixin() })

  /**
   * 发起支付并按渠道消费 `payment_data`；返回结果供调用方决定提示文案与后续跳转
   * （各插件调用点各自处理，见页面内注释）。
   */
  async function pay(orderNo: string, channel: PayChannel, payAmount: string): Promise<PayOutcome> {
    paying.value = true
    try {
      const tradeType = resolveTradeType(channel)
      const result = await payFn(orderNo, { channel, trade_type: tradeType })
      const action = resolvePaymentAction(channel, result.payment_data)

      switch (action.kind) {
        case 'mock': {
          await notifyMock(action.outTradeNo || orderNo, payAmount)
          uni.showToast({ title: '支付成功(mock)', icon: 'none' })
          return 'success'
        }
        case 'h5Redirect': {
          // #ifdef H5
          if (action.url) {
            window.location.href = action.url
          }
          // #endif
          return 'success'
        }
        case 'native': {
          await new Promise<void>((resolve) => {
            uni.showModal({
              title: '扫码支付',
              content: action.codeUrl || '请使用扫码工具完成支付',
              showCancel: false,
              success: () => resolve(),
              fail: () => resolve(),
            })
          })
          // native 需要用户离线扫码，当前会话内无法确认支付结果，按"可再支付"处理
          return 'fail'
        }
        case 'requestPayment': {
          await uni.requestPayment({
            provider: action.provider,
            ...(action.params as any),
          })
          return 'success'
        }
        default:
          uni.showToast({ title: '暂不支持的支付方式', icon: 'none' })
          return 'fail'
      }
    } catch (error: any) {
      if (error?.errMsg?.includes('cancel')) {
        uni.showToast({ title: '已取消支付', icon: 'none' })
        return 'cancel'
      }
      uni.showToast({ title: error?.message || '支付失败', icon: 'none' })
      return 'fail'
    } finally {
      paying.value = false
    }
  }

  return { paying, channelOptions, pay }
}
