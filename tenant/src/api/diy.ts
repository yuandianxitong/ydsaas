import { myRequest } from '@/utils/request'

export interface DiyComponent {
    id: string
    type: string
    props: Record<string, any>
    hidden?: boolean
}

export interface DiyPopupAd {
    enabled: boolean
    display_type: 'first' | 'every'
    image: string
    link: string
}

export interface DiyPageSettings {
    background_color?: string
    background_image?: string
    show_header?: boolean
    popup_ad?: DiyPopupAd
}

export interface DiyHome {
    components: DiyComponent[]
    page_settings: DiyPageSettings
    /** 页面标题（diy_pages.title）；草稿读写一并携带 */
    title?: string
}

export interface DiyVersion {
    id: number
    version_no: number
    created_at: string
    note?: string
}

export interface DiyHomeSummary {
    title: string
    published: boolean
    component_count: number
    updated_at: string | null
}

export interface DiyPageListItem {
    id: number
    page_key: string
    title: string
    status: number
    updated_at: string
    component_count: number
    published: boolean
}

export interface PluginWidgetField {
    key: string
    label: string
    type:
        | 'number'
        | 'text'
        | 'select'
        | 'radio'
        | 'link'
        | 'image'
        | 'color'
        | 'switch'
        | 'api-select'
        | 'api-multi-select'
        | 'style-preset'
        | 'checkbox-group'
        | 'goods-picker'
    options?: Array<{
        label: string
        value: any
        /** 样式预设缩略图 URL（type=style-preset） */
        thumb?: string
        /** 选中预设时合并到 props / componentStyle 的补丁 */
        patch?: {
            props?: Record<string, unknown>
            componentStyle?: Record<string, unknown>
        }
    }>
    options_url?: string
    show_if?: { key: string; value: any }
    /** 属性面板分组标题（连续同 section 合并为一个区块） */
    section?: string
    /** 控件下方灰色说明 */
    hint?: string
    /** 下拉占位文案 */
    placeholder?: string
    /** api-select 清空/未选写回值（默认 0） */
    empty_value?: 0 | '' | null
}
export interface PluginWidgetMeta {
    type: string
    label: string
    render: string
    /** 插件 code（渲染器协议 v1：宿主按 plugin + renderer.tenant 解析插件自带预览渲染器） */
    plugin: string
    /** manifest diy_widgets[].renderer 声明（protocol + 各端裸组件名），未声明为 null */
    renderer?: { protocol?: number; tenant?: string; uniapp?: string; pc?: string } | null
    /** 面板图标 URL（widget 声明 → 插件 logo → '' 三级回退，'' 时前端用占位图标） */
    icon_url?: string
    default_props: Record<string, any>
    props_schema: PluginWidgetField[]
    pages?: string[]
}

export interface MemberStatOption {
    key: string
    label: string
    plugin: string
}

export interface DiyWidgetCatalog {
    builtins: string[]
    plugins: PluginWidgetMeta[]
    member_stats: MemberStatOption[]
}

// home 走既有端点，自定义页走 /pages/:key —— 在此集中映射，组件只传 key
function draftUrl(key: string) {
    return key === 'home' ? '/tenantapi/diy/home' : `/tenantapi/diy/pages/${key}/draft`
}
function publishUrl(key: string) {
    return key === 'home' ? '/tenantapi/diy/home/publish' : `/tenantapi/diy/pages/${key}/publish`
}
function versionsUrl(key: string) {
    return key === 'home' ? '/tenantapi/diy/home/versions' : `/tenantapi/diy/pages/${key}/versions`
}
function restoreUrl(key: string, id: number) {
    return key === 'home'
        ? `/tenantapi/diy/home/versions/${id}/restore`
        : `/tenantapi/diy/pages/${key}/versions/${id}/restore`
}

export interface CatalogLink {
    label: string
    path: string
    category: string
    source: 'builtin' | 'custom-page' | 'plugin' | 'plugin-link' | 'library'
    params_schema: Array<{ key: string; label: string; type: string; required?: boolean }>
    external: boolean
}

export interface DiyLinkItem {
    id: number
    label: string
    path: string
    category: string
    icon?: string | null
    sort: number
    status: number
}

export const diyApi = {
    getHome() {
        return myRequest.get<DiyHome>('/tenantapi/diy/home')
    },
    /** 页面装修列表：home 状态摘要 */
    getHomeSummary() {
        return myRequest.get<DiyHomeSummary>('/tenantapi/diy/home/summary')
    },
    /** 系统页状态摘要（home/member） */
    getPageSummary(key: string) {
        return myRequest.get<DiyHomeSummary>(`/tenantapi/diy/pages/${key}/summary`)
    },
    saveHome(data: DiyHome) {
        return myRequest.put('/tenantapi/diy/home', data)
    },
    publishHome(data?: { note: string }) {
        return myRequest.post('/tenantapi/diy/home/publish', data)
    },
    listVersions() {
        return myRequest.get<DiyVersion[]>('/tenantapi/diy/home/versions')
    },
    restoreVersion(id: number) {
        return myRequest.post(`/tenantapi/diy/home/versions/${id}/restore`)
    },
    // 按 key 的草稿/发布/版本（home 或自定义页通用）
    getPageDraft(key: string) {
        return myRequest.get<DiyHome>(draftUrl(key))
    },
    savePageDraft(key: string, data: DiyHome) {
        return myRequest.put(draftUrl(key), data)
    },
    publishPage(key: string, data: { note: string }) {
        return myRequest.post(publishUrl(key), data)
    },
    listPageVersions(key: string) {
        return myRequest.get<DiyVersion[]>(versionsUrl(key))
    },
    restorePageVersion(key: string, id: number) {
        return myRequest.post(restoreUrl(key, id))
    },
    // 页面管理
    listPages(params?: { page?: number; limit?: number; keyword?: string; published?: 0 | 1 | '' }) {
        return myRequest.get<{ list: DiyPageListItem[]; total: number }>('/tenantapi/diy/pages', {
            params
        })
    },
    /** 复制自定义页（副本恒为未发布草稿，标识自动生成 <源>-copyN） */
    copyPage(id: number) {
        return myRequest.post<{ id: number }>(`/tenantapi/diy/pages/${id}/copy`)
    },
    createPage(data: { title: string; page_key: string }) {
        return myRequest.post<{ id: number }>('/tenantapi/diy/pages', data)
    },
    updatePage(id: number, data: { title?: string; page_key?: string; status?: number }) {
        return myRequest.put(`/tenantapi/diy/pages/${id}`, data)
    },
    deletePage(id: number) {
        return myRequest.delete(`/tenantapi/diy/pages/${id}`)
    },
    getWidgets() {
        return myRequest.get<DiyWidgetCatalog>('/tenantapi/diy/widgets')
    },
    /** 编辑器画布预览注水：单组件跑后端 hydrator 返回真实数据 props（与 C 端下发同一份注水逻辑） */
    previewWidget(type: string, props: Record<string, any>) {
        return myRequest.post<{ props: Record<string, any> }>('/tenantapi/diy/widget-preview', {
            type,
            props
        })
    },
    getLinkCatalog() {
        return myRequest.get<{ links: CatalogLink[] }>('/tenantapi/diy/link-catalog')
    },
    listLinks() {
        return myRequest.get<DiyLinkItem[]>('/tenantapi/diy/links')
    },
    createLink(data: Partial<DiyLinkItem>) {
        return myRequest.post<{ id: number }>('/tenantapi/diy/links', data)
    },
    updateLink(id: number, data: Partial<DiyLinkItem>) {
        return myRequest.put(`/tenantapi/diy/links/${id}`, data)
    },
    deleteLink(id: number) {
        return myRequest.delete(`/tenantapi/diy/links/${id}`)
    },

    /** 导出整套皮肤包（zip Blob） */
    exportSkin(data?: { code?: string; name?: string; include_custom?: boolean }) {
        return myRequest.post<Blob>('/tenantapi/diy/skin/export', data ?? {}, {
            responseType: 'blob',
            timeout: 120000
        }) as unknown as Promise<Blob>
    },

    /** 上传皮肤包预检 */
    importSkin(file: File) {
        const form = new FormData()
        form.append('file', file)
        return myRequest.post<SkinImportPreview>('/tenantapi/diy/skin/import', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            timeout: 120000
        })
    },

    /** 按预检 token 套用皮肤包 */
    applySkin(token: string) {
        return myRequest.post<{
            applied_pages: Array<{ platform: string; page_key: string; from: string }>
            renamed: Record<string, string>
            hint: string
        }>('/tenantapi/diy/skin/apply', { token })
    },

    /** Site 主题市场列表 */
    listMarketThemes(params?: Record<string, any>) {
        return myRequest.get<{ list: MarketThemeItem[]; pagination?: any }>('/tenantapi/diy/skin/market', {
            params
        })
    },

    /** 从官方市场安装主题 */
    installMarketTheme(data: { code: string; version?: string; auto_apply?: boolean }) {
        return myRequest.post('/tenantapi/diy/skin/market/install', data, { timeout: 120000 })
    }
}

export interface MarketThemeItem {
    id: number
    code: string
    name: string
    summary?: string
    cover?: string
    icon?: string
    is_free?: boolean
    price_cents?: number
    recommended_for_app?: string | null
}

export interface SkinImportPreview {
    ok: boolean
    token: string
    manifest: {
        code: string
        name: string
        version: string
        framework_saas_min?: string
        requires_apps?: string[]
        recommended_for_app?: string | null
        platforms?: string[]
        pages?: string[]
    }
    mobile: {
        theme_color?: string
        theme_colors?: Record<string, string>
        tabbar_count?: number
        home_app_code?: string
        home_page?: string
    }
    pages: Array<{
        platform: string
        page_key: string
        title: string
        page_type: string
        component_count: number
        widget_types: string[]
    }>
    missing_apps: string[]
    missing_widgets: string[]
    blocking_errors: string[]
    warnings: string[]
}
