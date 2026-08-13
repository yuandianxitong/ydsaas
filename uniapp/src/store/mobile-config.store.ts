import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { mobileConfigApi, type MobileConfig } from '@/api/mobile-config'
import { tenantConfig } from '@/generated/tenant-config'
import { applyMobileTheme } from '@/utils/mobile-theme'

const DEFAULT_CONFIG: MobileConfig = {
    app_name: '',
    app_logo: '',
    app_intro: '',
    theme_color: '',
    theme_colors: {},
    service_type: '',
    service_phone: '',
    share_title: '',
    share_image: '',
    home_app_code: '',
    home_page: '',
    tabbar: [],
    tabbar_style: {},
    home_decoration: null,
}

/** 软配置字段：API 有值则覆盖；缺省则保留注入/当前值（兼容旧网关）。 */
const SOFT_KEYS: (keyof MobileConfig)[] = [
    'app_name',
    'app_logo',
    'app_intro',
    'theme_color',
    'theme_colors',
    'service_type',
    'service_phone',
    'share_title',
    'share_image',
    'home_app_code',
    'home_page',
    'tabbar',
    'tabbar_style',
    'home_decoration',
]

/**
 * 构建期注入的租户配置（来源 src/generated/tenant-config.ts）。
 *
 * - 开发态：仓库里 commit 了一份空 stub（tenantId=0），等于 DEFAULT_CONFIG
 * - 独立构建：server/core/mobile/TenantConfigWriter 在产物里覆盖为真实租户配置（首屏兜底）
 *
 * 键名映射：tenant-config.ts 用 camelCase；MobileConfig 用 snake_case
 */
function readInjectedConfig(): Partial<MobileConfig> {
    const t: any = tenantConfig
    if (!t || !t.tenantId) return {}
    return {
        app_name: String(t.appName ?? ''),
        app_logo: String(t.appLogo ?? ''),
        app_intro: String(t.appIntro ?? ''),
        theme_color: String(t.themeColor ?? ''),
        theme_colors: t.themeColors && typeof t.themeColors === 'object' ? t.themeColors : {},
        service_type: (t.serviceType ?? '') as MobileConfig['service_type'],
        service_phone: String(t.servicePhone ?? ''),
        share_title: String(t.shareTitle ?? ''),
        share_image: String(t.shareImage ?? ''),
        home_app_code: String(t.homeAppCode ?? ''),
        home_page: String(t.homePage ?? ''),
        tabbar: Array.isArray(t.tabbar) ? t.tabbar : [],
        tabbar_style: t.tabbarStyle && typeof t.tabbarStyle === 'object' ? t.tabbarStyle : {},
        home_decoration: t.homeDecoration && typeof t.homeDecoration === 'object' ? t.homeDecoration : null,
    }
}

function mergeSoftConfig(base: MobileConfig, data: Partial<MobileConfig> | null | undefined): MobileConfig {
    const next: MobileConfig = { ...base }
    if (!data || typeof data !== 'object') return next

    for (const key of SOFT_KEYS) {
        if (!Object.prototype.hasOwnProperty.call(data, key)) continue
        const value = data[key]
        if (value === undefined) continue
        if (key === 'tabbar') {
            next.tabbar = Array.isArray(value) ? (value as MobileConfig['tabbar']) : next.tabbar
            continue
        }
        if (key === 'theme_colors' || key === 'tabbar_style') {
            ;(next as any)[key] = value && typeof value === 'object' ? value : next[key]
            continue
        }
        ;(next as any)[key] = value
    }
    return next
}

/**
 * 租户移动端运行时配置：
 *
 *   - 构建期注入作为首屏兜底（避免闪烁 / 弱网）
 *   - load() 始终请求 /api/mobile/config，用软字段覆盖注入值
 *   - 装修发布、主题、底部导航、启动入口等保存后，用户刷新/重新进入即可生效
 *   - 结构变更（新页面进包、分包）仍需打包发布
 */
export const useMobileConfigStore = defineStore('mobile-config', () => {
    const initial = { ...DEFAULT_CONFIG, ...readInjectedConfig() }
    const config = ref<MobileConfig>(initial)
    /** 是否已完成至少一次 load 尝试（注入首屏也算已有可用值） */
    const loaded = ref(Object.keys(readInjectedConfig()).length > 0)
    let inflight: Promise<MobileConfig> | null = null

    async function load(_force = false): Promise<MobileConfig> {
        if (inflight) return inflight
        inflight = mobileConfigApi
            .get()
            .then((res: any) => {
                const data: MobileConfig = res?.data ?? res
                config.value = mergeSoftConfig(
                    { ...DEFAULT_CONFIG, ...readInjectedConfig(), ...config.value },
                    data,
                )
                loaded.value = true
                applyMobileTheme(config.value.theme_color || '#2979ff', config.value.theme_colors || {})
                return config.value
            })
            .catch((err) => {
                console.warn('[mobile-config] load failed, keep injected/default', err)
                loaded.value = true
                applyMobileTheme(config.value.theme_color || '#2979ff', config.value.theme_colors || {})
                return config.value
            })
            .finally(() => {
                inflight = null
            })
        return inflight
    }

    function reset() {
        config.value = { ...DEFAULT_CONFIG, ...readInjectedConfig() }
        loaded.value = Object.keys(readInjectedConfig()).length > 0
        inflight = null
    }

    const themeColor = computed(() => config.value.theme_color || '#2979ff')
    const appName = computed(() => config.value.app_name)
    const appLogo = computed(() => config.value.app_logo)
    const homePage = computed(() => config.value.home_page)
    const tabbar = computed(() => config.value.tabbar)
    const themeColors = computed(() => config.value.theme_colors || {})
    const tabbarStyle = computed(() => config.value.tabbar_style || {})
    const shareTitle = computed(() => config.value.share_title || config.value.app_name || '')
    const shareImage = computed(() => config.value.share_image || config.value.app_logo || '')
    const serviceType = computed(() => config.value.service_type || '')
    const servicePhone = computed(() => config.value.service_phone || '')

    return {
        config,
        loaded,
        load,
        reset,
        themeColor,
        appName,
        appLogo,
        homePage,
        tabbar,
        themeColors,
        tabbarStyle,
        shareTitle,
        shareImage,
        serviceType,
        servicePhone,
    }
})
