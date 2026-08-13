import { getToken } from '@/utils/auth'
import { tenantConfig } from '@/generated/tenant-config'

/**
 * 与 utils/request.ts 保持一致的 BASE_URL 解析策略：
 * H5 始终相对路径（同域 /api）；小程序/App 使用 VITE_APP_API_URL。
 */
let BASE_URL = import.meta.env.VITE_APP_API_URL || ''
// #ifdef H5
BASE_URL = ''
// #endif

export const uploadApi = {
  uploadImage: (filePath: string): Promise<{ url: string; path: string }> => {
    return new Promise((resolve, reject) => {
      uni.uploadFile({
        url: `${BASE_URL}/api/common/upload/image`,
        filePath,
        name: 'file',
        header: {
          Authorization: `Bearer ${getToken()}`,
          ...(tenantConfig.tenantCode ? { 'X-Tenant-Code': tenantConfig.tenantCode } : {}),
        },
        success: (res) => {
          try {
            const data = JSON.parse(res.data)
            if (data.code === 200) {
              resolve(data.data)
            } else {
              // 不透传后端 message：uni.uploadFile 不按响应 charset 解码（DCloud 已知行为），
              // 中文 message 会乱码，统一用前端本地文案
              reject(new Error('上传失败'))
            }
          } catch {
            reject(new Error('上传响应解析失败'))
          }
        },
        fail: reject,
      })
    })
  },
}
