import http from '@/utils/request'
import type { LoginResult, UserInfo } from '@/types/api'

export const authApi = {
  login: (data: { mobile: string; password: string }) =>
    http.post<LoginResult>('/api/auth/login', data),

  smsLogin: (data: { mobile: string; code: string }) =>
    http.post<LoginResult>('/api/auth/sms-login', data),

  sendSmsCode: (data: { mobile: string; scene?: string }) =>
    http.post('/api/common/sms-code', data),

  wechatMiniLogin: (data: { code: string }) =>
    http.post<LoginResult>('/api/auth/wechat-login', data),

  register: (data: { mobile: string; code: string; password: string; password_confirmation: string }) =>
    http.post<LoginResult>('/api/auth/register', data),

  refreshToken: () =>
    http.post<{ token: string }>('/api/auth/refresh-token'),

  getUserInfo: () =>
    http.get<UserInfo>('/api/auth/info'),

  logout: () =>
    http.post('/api/auth/logout'),

  wechatQuickLogin(data: { code: string }) {
    return http.post<WechatQuickLoginResult>('/api/auth/wechat-quick-login', data)
  },
  wechatBindPhone(data: { temp_token: string; phone_code: string }) {
    return http.post<WechatBindPhoneResult>('/api/auth/wechat-bindphone', data)
  },
}

export interface WechatQuickLoginResult {
  status: 'logged_in' | 'need_bindphone'
  token?: string
  temp_token?: string
  need_profile?: boolean
  /** logged_in 时后端带回的用户摘要（昵称/头像回显用；无则弹窗不回显） */
  user_info?: { id: number; nickname: string; avatar: string; mobile: string }
}
export interface WechatBindPhoneResult {
  status: 'logged_in'
  token: string
  mobile: string
  need_profile?: boolean
  user_info?: { id: number; nickname: string; avatar: string; mobile: string }
}
