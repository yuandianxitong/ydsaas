import { getToken, removeToken } from './auth'
import type { ApiResponse } from '@/types/api'
import { tenantConfig } from '@/generated/tenant-config'

let BASE_URL = import.meta.env.VITE_APP_API_URL || ''
// #ifdef H5
// 租户 H5 部署在 {tenant}.{root}/mobile/，/api 由同域 nginx 反代；始终相对路径，
// 避免 .env.production 误配平台域（如 admin.*）导致跨域/租户上下文丢失。
BASE_URL = ''
// #endif

/**
 * API 基址（只读导出）。上传文件由 API 服务器伺服（与 API 同源），
 * 图片相对路径兜底拼接用（见 app.store getImageUrl）；H5 dev 下为空串走同源代理。
 */
export const API_BASE_URL = BASE_URL

function getClientType(): string {
  // #ifdef MP-WEIXIN
  return 'miniapp'
  // #endif
  // #ifdef APP-PLUS
  return 'app'
  // #endif
  // #ifdef H5
  const ua = navigator.userAgent.toLowerCase()
  return ua.includes('micromessenger') ? 'wechat_h5' : 'h5'
  // #endif
}

interface RequestOptions {
  url: string
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE'
  data?: any
  header?: Record<string, string>
  loading?: boolean
}

function request<T = any>(options: RequestOptions): Promise<T> {
  const { url, method = 'GET', data, header = {}, loading = false } = options

  if (loading) {
    uni.showLoading({ title: '加载中...' })
  }

  const token = getToken()
  if (token) {
    header['Authorization'] = `Bearer ${token}`
  }
  if (tenantConfig.tenantCode) {
    header['X-Tenant-Code'] = tenantConfig.tenantCode
  }

  return new Promise((resolve, reject) => {
    uni.request({
      url: `${BASE_URL}${url}`,
      method,
      data,
      header: {
        'Content-Type': 'application/json',
        'X-Client-Type': getClientType(),
        ...header,
      },
      success: (res: any) => {
        if (loading) uni.hideLoading()

        const response = res.data as ApiResponse<T>

        if (response.code === 200) {
          resolve(response.data)
        } else if (response.code === 401 || res.statusCode === 401) {
          removeToken()
          uni.reLaunch({ url: '/modules/login/pages/login' })
          reject(new Error(response.message || '请先登录'))
        } else {
          uni.showToast({ title: response.message || '请求失败', icon: 'none' })
          reject(new Error(response.message))
        }
      },
      fail: (err: any) => {
        if (loading) uni.hideLoading()
        uni.showToast({ title: '网络异常', icon: 'none' })
        reject(err)
      },
    })
  })
}

export const http = {
  get: <T = any>(url: string, data?: any) => request<T>({ url, method: 'GET', data }),
  post: <T = any>(url: string, data?: any) => request<T>({ url, method: 'POST', data }),
  put: <T = any>(url: string, data?: any) => request<T>({ url, method: 'PUT', data }),
  delete: <T = any>(url: string, data?: any) => request<T>({ url, method: 'DELETE', data }),
}

export default http
