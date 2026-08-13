import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { getToken, setToken, removeToken } from '@/utils/auth'
import { authApi } from '@/api/auth'
import type { UserInfo, LoginResult } from '@/types/api'

export const useUserStore = defineStore('user', () => {
  const token = ref(getToken())
  const userInfo = ref<UserInfo | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const nickname = computed(() => userInfo.value?.nickname || '')
  const avatar = computed(() => userInfo.value?.avatar || '')

  async function login(params: { mobile: string; password: string }): Promise<LoginResult> {
    const result = await authApi.login(params)
    applyLoginResult(result)
    await afterLogin()
    return result
  }

  async function smsLogin(params: { mobile: string; code: string }): Promise<LoginResult> {
    const result = await authApi.smsLogin(params)
    applyLoginResult(result)
    await afterLogin()
    return result
  }

  /**
   * 注册：手机号 + 验证码 + 密码
   *
   * 成功后自动完成登录流程（写入 token、userInfo、触发 afterLogin 钩子），
   * 调用方无需再直接操作 store 字段。
   */
  async function register(params: {
    mobile: string
    code: string
    password: string
    password_confirmation: string
  }): Promise<LoginResult> {
    const result = await authApi.register(params)
    applyLoginResult(result)
    await afterLogin()
    return result
  }

  /** 统一的登录结果写入逻辑 */
  function applyLoginResult(result: LoginResult) {
    token.value = result.token
    userInfo.value = result.user_info ?? null
    setToken(result.token)
  }

  /**
   * 仅拿到 token 的登录场景（微信快捷登录 / 绑定手机号）：
   * 写入 store + 持久化后后台拉取用户信息。isLoggedIn 只依赖 token，
   * 同步翻转即生效；getUserInfo 不 await——弱网下阻塞会把登录跳转
   * 卡在平台默认超时上，用户信息由页面稍后展示/重拉即可。
   */
  function applyToken(newToken: string): void {
    token.value = newToken
    setToken(newToken)
    getUserInfo().catch(() => {
      // 拉取用户信息失败不影响登录态
    })
  }

  /** 登录成功后的通用钩子 */
  async function afterLogin() {
    // H5 微信浏览器：绑定 oa_openid 到当前用户
    // #ifdef H5
    import('@/utils/wechat-oauth').then(({ bindOaOpenidAfterLogin }) => {
      bindOaOpenidAfterLogin()
    }).catch(() => {})
    // #endif
  }

  async function getUserInfo(): Promise<UserInfo> {
    const result = await authApi.getUserInfo()
    userInfo.value = result
    return result
  }

  function logout(options?: { redirect?: boolean }) {
    authApi.logout().catch(() => {})
    token.value = ''
    userInfo.value = null
    removeToken()
    if (options?.redirect !== false) {
      uni.reLaunch({ url: '/modules/login/pages/login' })
    }
  }

  return { token, userInfo, isLoggedIn, nickname, avatar, login, smsLogin, register, applyToken, getUserInfo, logout }
})
