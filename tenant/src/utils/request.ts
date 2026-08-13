import type { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios'
import axios from 'axios'
import { ElMessage } from 'element-plus'

import { PageEnum } from '@/constants/page'
import { getLocale } from '@/locales/setupI18n'
import router from '@/router'
import type { ApiResponse } from '@/types/api'
import { clearAuthInfo, getToken } from '@/utils/auth'
import { t } from '@/utils/i18n'

// 创建axios实例
// 开发环境：留空，通过 vite proxy 代理
// 生产环境：读取 VITE_APP_API_URL，留空则请求当前域名（同域部署），填写则请求指定域名（跨域部署）
const request: AxiosInstance = axios.create({
    baseURL: import.meta.env.DEV ? '' : import.meta.env.VITE_APP_API_URL || '',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json;charset=UTF-8',
        Accept: 'application/json'
    }
})

// ========== Token 静默刷新 ==========
// 防止"用户编辑文章过程中 token 过期，保存时被踢出"的体验问题：
// 任何业务请求发出前，若当前 token 剩余寿命 < 50%，先静默调一次 refresh 拿新 token，
// 再用新 token 继续原请求。并发请求只会触发一次实际刷新。
const REFRESH_URL = '/tenantapi/auth/refresh'
let refreshingPromise: Promise<void> | null = null

function parseJwtPayload(token: string): Record<string, any> | null {
    try {
        const parts = token.split('.')
        if (parts.length !== 3) return null
        const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/')
        return JSON.parse(atob(base64))
    } catch {
        return null
    }
}

function shouldRefresh(token: string): boolean {
    const payload = parseJwtPayload(token)
    if (!payload?.exp || !payload?.iat) return false
    const now = Math.floor(Date.now() / 1000)
    const remaining = payload.exp - now
    const total = payload.exp - payload.iat
    return remaining > 0 && remaining < total * 0.5
}

async function doRefresh(): Promise<void> {
    if (refreshingPromise) return refreshingPromise
    refreshingPromise = (async () => {
        try {
            const { useUserStore } = await import('@/store/modules/user.store')
            await useUserStore().refreshToken()
        } finally {
            refreshingPromise = null
        }
    })()
    return refreshingPromise
}

// 请求拦截器
request.interceptors.request.use(
    async (config: any) => {
        // 添加Token（从统一缓存读取）
        let token = getToken()

        // 静默刷新：非 refresh 请求且 token 即将过期时自动刷新
        if (token && config.url !== REFRESH_URL && shouldRefresh(token)) {
            try {
                await doRefresh()
                token = getToken() // 刷新后重新读取
            } catch {
                // 刷新失败时继续使用旧 token，由响应拦截器 401 流程接管
            }
        }

        if (token && config.headers) {
            config.headers.Authorization = `Bearer ${token}`
        }

        // 添加当前语言（同步前后端多语言）
        const locale = getLocale()
        if (config.headers) {
            const thinkLang = locale === 'zh-CN' ? 'zh-cn' : 'en'
            config.headers['think-lang'] = thinkLang
        }

        // 添加traceId
        const traceId = generateTraceId()
        if (config.headers) {
            config.headers['X-Trace-Id'] = traceId
        }

        // FormData 必须让浏览器自动带 multipart boundary；默认 application/json 会破坏上传
        if (typeof FormData !== 'undefined' && config.data instanceof FormData && config.headers) {
            delete config.headers['Content-Type']
            delete config.headers['content-type']
        }

        return config
    },
    (error) => {
        console.error('Request error:', error)
        return Promise.reject(error)
    }
)

// 响应拦截器
request.interceptors.response.use(
    (response: AxiosResponse<ApiResponse>) => {
        const { data } = response
        // 文件下载（皮肤包等）：直接返回 Blob，不做业务码解析
        if (response.config.responseType === 'blob' || data instanceof Blob) {
            return data as any
        }
        // 检查业务状态码
        if (data.code === 200 || data.code === 0) {
            return data as any
        }

        // 处理token验证失败的业务错误码
        if (
            data.code === 401 ||
            data.message?.includes('Token验证失败') ||
            data.message?.includes('Expired token')
        ) {
            // 使用统一的认证清理函数
            clearAuthInfo()
            // 避免在登录页面时重复跳转
            if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                router.push(PageEnum.LOGIN)
            }
            const err = new Error(t('http.loginExpired'))
            ;(err as any).__handled = true
            return Promise.reject(err)
        }

        // 其他业务错误处理
        ElMessage.error(data.message || t('http.operationFailed'))
        const err = new Error(data.message || t('http.operationFailed'))
        ;(err as any).__handled = true
        return Promise.reject(err)
    },
    (error) => {
        // 主动取消（工作台趋势切换等）不弹错、不记未处理
        if (
            axios.isCancel?.(error) ||
            error?.code === 'ERR_CANCELED' ||
            error?.name === 'CanceledError'
        ) {
            ;(error as any).__handled = true
            return Promise.reject(error)
        }

        let message = t('http.requestFailed')

        if (error.response) {
            const { status, data } = error.response

            switch (status) {
                case 401:
                    message = t('http.loginExpired')
                    // 使用统一的认证清理函数
                    clearAuthInfo()
                    // 避免在登录页面时重复跳转
                    if (router.currentRoute.value.path !== PageEnum.LOGIN) {
                        router.push(PageEnum.LOGIN)
                    }
                    break
                case 402:
                    // 租户生命周期拦截（grace 只读 / frozen 冻结）：后端 message 已是准确提示。
                    // 动态 import 避免 store ↔ request 循环依赖；刷新 saas 状态让顶部
                    // 告警条（含「立即续费」入口）立即出现。
                    message = data?.message || t('http.tenantRestricted')
                    import('@/store/modules/user.store')
                        .then(({ default: useUserStore }) => useUserStore().getUserInfo())
                        .catch(() => {})
                    break
                case 403:
                    message = t('http.forbidden')
                    break
                case 404:
                    message = t('http.notFound')
                    break
                case 422:
                    message = data?.message || t('http.validationFailed')
                    break
                case 400:
                    message = data?.message || t('http.badRequest')
                    break
                case 500:
                    message = data?.message || t('http.serverError')
                    break
                case 503:
                    // 系统未安装，提示用户访问后端安装页面
                    if (data?.data?.installed === false) {
                        message = t('http.notInstalled')
                    } else {
                        message = t('http.serviceUnavailable')
                    }
                    break
                default:
                    message = data?.message || `${t('http.requestFailed')} (${status})`
            }
        } else if (error.request) {
            message = t('http.networkError')
        }

        ElMessage.error(message)
        return Promise.reject(error)
    }
)

// 生成traceId
function generateTraceId(): string {
    return 'trace_' + Date.now() + '_' + Math.random().toString(36).substring(2, 11)
}

// ========== GET 请求去重 ==========
const pendingGetRequests = new Map<string, Promise<any>>()

function buildDedupKey(url: string, config?: AxiosRequestConfig): string {
    const params = config?.params ? JSON.stringify(config.params) : ''
    return `${url}:${params}`
}

// 封装请求方法
export const myRequest = {
    get<T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        // 带 signal 的请求不参与去重：AbortController 与共享 Promise 无法安全共存
        if (config?.signal) {
            return request.get(url, config) as unknown as Promise<ApiResponse<T>>
        }
        const key = buildDedupKey(url, config)
        const pending = pendingGetRequests.get(key)
        if (pending) {
            return pending as Promise<ApiResponse<T>>
        }
        const req = request.get(url, config).finally(() => {
            pendingGetRequests.delete(key)
        }) as unknown as Promise<ApiResponse<T>>
        pendingGetRequests.set(key, req)
        return req
    },

    post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.post(url, data, config)
    },

    put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.put(url, data, config)
    },

    delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.delete(url, config)
    },

    patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
        return request.patch(url, data, config)
    }
}

export default request
