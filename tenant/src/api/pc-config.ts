import { myRequest } from '@/utils/request'

export interface PcNavItem {
    label: string
    path: string
    code?: string
    auth?: boolean
    sort?: number
}

export interface PcConfig {
    site_name: string
    site_logo: string
    site_intro: string
    theme_color: string
    home_type: 'diy' | 'app' | 'redirect'
    home_app_code: string
    home_page: string
    nav: PcNavItem[]
    seo: {
        title?: string
        keywords?: string
        description?: string
    }
    login_enabled: boolean
    register_enabled: boolean
    status?: number
}

export interface PcPluginPage {
    route: string
    title: string
    nav: boolean
    auth: boolean
}

export interface PcPluginOption {
    code: string
    plugin_code: string
    name: string
    kind: 'app' | 'plugin'
    allowHome: boolean
    home: string
    pages: PcPluginPage[]
}

export interface PcConfigOptions {
    homeOptions: PcPluginOption[]
    navOptions: PcPluginOption[]
    fallback: { type: string; page_key: string }
}

export const pcConfigApi = {
    get() {
        return myRequest.get<PcConfig>('/tenantapi/pc/config')
    },
    update(data: Partial<PcConfig>) {
        return myRequest.put<PcConfig>('/tenantapi/pc/config', data)
    },
    options() {
        return myRequest.get<PcConfigOptions>('/tenantapi/pc/config/options')
    },
}
