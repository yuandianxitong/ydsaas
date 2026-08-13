// 构建期注入的租户配置 stub。
//
// 开发态：tenantId=0 表示「未注入」，store 忽略注入、依赖 /api/mobile/config。
// 独立构建：TenantConfigWriter 覆盖为真实租户配置，作首屏兜底；
// store.load() 仍会拉 /api/mobile/config 覆盖软字段（装修/主题/tabBar/启动入口）。
//
// 注意：该文件 commit 在仓库里只是为了让 store 静态 import 不报错；不要手工编辑。
export const tenantConfig = {
    tenantId: 0,
    tenantCode: '',
    appName: '',
    appLogo: '',
    appIntro: '',
    themeColor: '',
    themeColors: {} as Record<string, string>,
    serviceType: '',
    servicePhone: '',
    shareTitle: '',
    shareImage: '',
    homePage: '',
    homeAppCode: '',
    tabbar: [] as Array<{
        code: string
        path: string
        text: string
        icon?: string
        selected_icon?: string
        sel_label?: string
        badge?: string
    }>,
    tabbarStyle: {} as Record<string, string>,
    homeDecoration: null as null | {
        components: Array<{ id: string; type: string; props: Record<string, any> }>
        page_settings?: Record<string, any>
    },
} as const

export type TenantConfig = typeof tenantConfig
