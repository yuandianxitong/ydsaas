import { defineStore } from 'pinia'
import { ref } from 'vue'
import { configApi } from '@/api/config'
import { API_BASE_URL } from '@/utils/request'

export const useAppStore = defineStore('app', () => {
  const config = ref<Record<string, any>>({})
  const isConfigLoaded = ref(false)
  let configPromise: Promise<Record<string, any>> | null = null

  async function getConfig() {
    if (isConfigLoaded.value) return config.value
    if (configPromise) return configPromise
    configPromise = configApi.getGlobalConfig().then((result) => {
      config.value = result
      isConfigLoaded.value = true
      return result
    }).finally(() => {
      configPromise = null
    })
    return configPromise
  }

  /**
   * 图片地址补全：相对路径按 site_url > oss_domain > API 同源域 兜底拼接。
   * 末位 API_BASE_URL 兜底（v2.25.x）：租户未配 site_url/oss_domain 时，上传文件
   * 由 API 服务器伺服（与 API 同源），小程序 <image> 不接受相对路径，必须补全域名；
   * H5 dev 下 API_BASE_URL 为空串，仍走同源代理，行为不变。
   */
  function getImageUrl(url: string): string {
    if (!url) return ''
    const baseUrl = config.value.site_url || config.value.oss_domain || API_BASE_URL || ''
    // 已是完整 URL：如果 site_url 已配置且 URL 以其他域名开头，替换为 site_url
    if (url.startsWith('http://') || url.startsWith('https://')) {
      if (baseUrl) {
        // 提取路径部分（从第一个 /storage/ 或 /uploads/ 开始）
        const pathMatch = url.match(/(\/storage\/.*)/)
        if (pathMatch) {
          return baseUrl + pathMatch[1]
        }
      }
      return url
    }
    return baseUrl + url
  }

  /** 清理内存中的全局配置，下次调用 getConfig 时会重新拉取 */
  function resetConfig() {
    config.value = {}
    isConfigLoaded.value = false
    configPromise = null
  }

  return { config, isConfigLoaded, getConfig, getImageUrl, resetConfig }
})
