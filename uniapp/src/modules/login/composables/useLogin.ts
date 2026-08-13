import { ref } from 'vue'
import { useUserStore } from '@/store/user.store'
import { authApi } from '@/api/auth'
import { userApi } from '@/api/user'
import { isMobile, isPassword, isVerifyCode } from '@/utils/validate'
import { maskMobile } from '@/components/d-wechat-auth-popup/helpers'
import { getToken } from '@/utils/auth'

// 模块级 in-flight 守卫：防止手机号授权回调双击/重复触发导致并发换 token
const authPhoneCodeInFlight = ref(false)

export function useLogin() {
  const userStore = useUserStore()
  const loading = ref(false)
  const loginType = ref<'password' | 'sms'>('password')
  const countdown = ref(0)
  const wechatQuickLoading = ref(false)
  const tempToken = ref('')

  const showAuthPopup = ref(false)
  const authPopupShowPhone = ref(false)
  const authPhoneDisplay = ref('')
  const authSubmitting = ref(false)
  const profileDefaults = ref({ nickname: '', avatar: '' })

  async function loginByPassword(mobile: string, password: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (!isPassword(password)) {
      uni.showToast({ title: '密码长度6-20位', icon: 'none' })
      return
    }

    loading.value = true
    try {
      await userStore.login({ mobile, password })
      uni.reLaunch({ url: '/pages/index/index' })
    } finally {
      loading.value = false
    }
  }

  async function loginBySms(mobile: string, code: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (!isVerifyCode(code)) {
      uni.showToast({ title: '请输入正确的验证码', icon: 'none' })
      return
    }

    loading.value = true
    try {
      await userStore.smsLogin({ mobile, code })
      uni.reLaunch({ url: '/pages/index/index' })
    } finally {
      loading.value = false
    }
  }

  async function sendCode(mobile: string) {
    if (!isMobile(mobile)) {
      uni.showToast({ title: '请输入正确的手机号', icon: 'none' })
      return
    }
    if (countdown.value > 0) return

    await authApi.sendSmsCode({ mobile })
    uni.showToast({ title: '验证码已发送', icon: 'none' })
    countdown.value = 60
    const timer = setInterval(() => {
      countdown.value--
      if (countdown.value <= 0) clearInterval(timer)
    }, 1000)
  }

  async function loginByWechatQuick() {
    wechatQuickLoading.value = true
    try {
      const loginRes = await new Promise<UniApp.LoginRes>((resolve, reject) => {
        uni.login({ provider: 'weixin', success: resolve, fail: reject })
      })
      const result = await authApi.wechatQuickLogin({ code: loginRes.code })

      if (result.status === 'logged_in' && result.token) {
        userStore.applyToken(result.token)
        if (result.need_profile) {
          // 资料不全：token 生效但停留登录页，弹窗保存后才进首页
          profileDefaults.value = {
            nickname: result.user_info?.nickname ?? '',
            avatar: result.user_info?.avatar ?? '',
          }
          authPopupShowPhone.value = false
          authPhoneDisplay.value = ''
          showAuthPopup.value = true
        } else {
          uni.reLaunch({ url: '/pages/index/index' })
        }
      } else if (result.status === 'need_bindphone') {
        tempToken.value = result.temp_token ?? ''
        profileDefaults.value = { nickname: '', avatar: '' }
        authPopupShowPhone.value = true
        authPhoneDisplay.value = ''
        showAuthPopup.value = true
      }
    } catch (e: any) {
      uni.showToast({ title: e.message || '登录失败', icon: 'none' })
    } finally {
      wechatQuickLoading.value = false
    }
  }

  /** 弹窗手机号授权：temp_token 换正式 token（不跳转），回显脱敏手机号
   * in-flight 守卫防双击/重复触发并发换 token（换 token 接口非幂等，并发请求可能互相覆盖） */
  async function handleAuthPhoneCode(code: string) {
    if (authPhoneCodeInFlight.value) return
    authPhoneCodeInFlight.value = true
    try {
      const result = await authApi.wechatBindPhone({ temp_token: tempToken.value, phone_code: code })
      if (result.token) {
        userStore.applyToken(result.token)
        authPhoneDisplay.value = maskMobile(result.mobile ?? '')
      }
    } catch (e: any) {
      uni.showToast({ title: e.message || '手机号绑定失败', icon: 'none' })
    } finally {
      authPhoneCodeInFlight.value = false
    }
  }

  /** 弹窗保存：完善资料成功才进首页；失败保持弹窗可重试 */
  async function handleAuthSubmit(payload: { nickname: string; avatar?: string }) {
    authSubmitting.value = true
    try {
      await userApi.updateProfile(payload)
      showAuthPopup.value = false
      uni.reLaunch({ url: '/pages/index/index' })
    } catch {
      // 拦截器已 toast；弹窗保持，允许重试或关闭放弃
    } finally {
      authSubmitting.value = false
    }
  }

  /** 用户主动关闭=放弃登录：丢 token 留在登录页（下次登录资料仍不全会再弹）
   * need_bindphone 场景下用户尚未授权手机号时从未 applyToken，此时无 token 可丢；
   * 若强行 logout 会因无 Authorization 触发全局 401 拦截器 reLaunch，丢失登录页输入态 */
  function handleAuthClose() {
    showAuthPopup.value = false
    tempToken.value = ''
    if (getToken()) {
      userStore.logout({ redirect: false })
    }
  }

  return {
    loading, loginType, countdown, loginByPassword, loginBySms, sendCode,
    wechatQuickLoading, loginByWechatQuick,
    showAuthPopup, authPopupShowPhone, authPhoneDisplay, authSubmitting, profileDefaults,
    handleAuthPhoneCode, handleAuthSubmit, handleAuthClose,
  }
}
