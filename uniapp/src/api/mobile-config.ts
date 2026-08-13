import http from '@/utils/request'

export interface MobileTabbarItem {
    code: string
    path: string
    text: string
    icon?: string
    selected_icon?: string
    sel_label?: string
    badge?: string
}

export interface ThemeColors {
    primary?: string
    dark?: string
    price?: string
    page_bg?: string
    button_text?: string
    badge?: string
}

export interface TabbarStyle {
    text_color?: string
    active_color?: string
    bg_color?: string
}

export interface MobileConfig {
    app_name: string
    app_logo: string
    app_intro?: string
    theme_color: string
    theme_colors?: ThemeColors
    service_type?: '' | 'online' | 'wechat' | 'phone'
    service_phone?: string
    share_title?: string
    share_image?: string
    home_app_code: string
    home_page: string
    tabbar: MobileTabbarItem[]
    tabbar_style?: TabbarStyle
    home_decoration?: { components: Array<{ id: string; type: string; props: Record<string, any> }>; page_settings?: Record<string, any> } | null
}

export interface DiyPagePayload {
    components: Array<{ id: string; type: string; props: Record<string, any> }>
    page_settings?: Record<string, any>
}

export const mobileConfigApi = {
    /**
     * 获取当前租户移动端配置。
     * 由后端按 Host subdomain 解析租户上下文，前端不需要传任何参数。
     */
    get: () => http.get<MobileConfig>('/api/mobile/config'),

    /** 按 slug 拉取某自定义装修页的已发布树。404 表示未发布/禁用/不存在。 */
    getDiyPage: (key: string) => http.get<DiyPagePayload>('/api/mobile/diy-page', { key }),
}
