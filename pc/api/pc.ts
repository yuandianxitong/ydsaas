import { get } from '~/composables/useRequest'

export interface PcNavItem {
  label: string
  path: string
  code?: string
  auth?: boolean
  sort?: number
}

export interface PcSiteConfig {
  site_name: string
  site_logo: string
  site_intro: string
  theme_color: string
  home_type: 'diy' | 'app' | 'redirect'
  home_app_code: string
  home_page: string
  nav: PcNavItem[]
  seo?: {
    title?: string
    keywords?: string
    description?: string
  }
  login_enabled: boolean
  register_enabled: boolean
  status: number
  fallback?: { type: string; page_key: string }
}

export const pcApi = {
  getConfig: () => get<PcSiteConfig>('/api/pc/config', undefined, false),
  getDiyPage: (key = 'home') => get('/api/pc/diy-page', { key }, false),
}
