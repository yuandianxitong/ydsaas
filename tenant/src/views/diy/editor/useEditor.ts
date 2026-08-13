import { computed, ref } from 'vue'

import type { DiyComponent, DiyPageSettings } from '@/api/diy'

import { defaultComponentStyle } from './styleUtils'

export const WIDGET_TYPES = [
    'banner',
    'nav-grid',
    'category-nav',
    'rich-text',
    'title-bar',
    'divider',
    'image-ad',
    'image-cube',
    'hotzone',
    'search-banner',
    'video',
    'notice',
    'search-bar',
    'float-button',
    'user-info-card',
    'service-menu'
] as const

export const TYPE_LABELS: Record<string, string> = {
    banner: '轮播图',
    'nav-grid': '图文导航',
    'category-nav': '分类导航',
    'rich-text': '富文本',
    'title-bar': '标题栏',
    divider: '辅助分割',
    'image-ad': '图片广告',
    'image-cube': '图片魔方',
    hotzone: '热区',
    'search-banner': '轮播搜索',
    video: '视频',
    notice: '公告',
    'search-bar': '搜索框',
    'float-button': '悬浮按钮',
    'user-info-card': '用户信息卡',
    'service-menu': '服务菜单'
}

export function genId(): string {
    return 'c_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6)
}

export function defaultAssets() {
    return [
        { label: '余额', stat_key: 'user.balance', link: '/modules/user/pages/balance' },
        { label: '积分', stat_key: 'user.points', link: '/modules/user/pages/points' }
    ]
}

/** 存量数据兜底：选中 user-info-card 时就地补 assets（与 StyleConfig.ensureFull 同思路） */
export function ensureUserInfoAssets(c: { type: string; props: Record<string, any> } | null) {
    if (c?.type === 'user-info-card' && !Array.isArray(c.props.assets)) c.props.assets = defaultAssets()
}

export function defaultProps(type: string): Record<string, any> {
    const base: Record<string, Record<string, any>> = {
        // banner.height 单位是 rpx（uniapp 直接使用，PC/画布 ÷2 显示）：300rpx = 实际显示 150px
        banner: {
            items: [],
            autoplay: true,
            interval: 3000,
            height: 300,
            indicator_style: 'dot',
            indicator_position: 'inside-bottom',
        },
        'nav-grid': { items: [], columns: 4 },
        // 分类导航（对齐元点Shop 实际渲染）：style=icon-grid 图标网格 / scroll 横向滚动；
        // rows 仅决定空数据占位数量（真实 items 按 columns 自动换行）
        'category-nav': { style: 'icon-grid', items: [], rows: 2, columns: 5 },
        'rich-text': { content: '' },
        'title-bar': { title: '', subtitle: '', align: 'left', more_text: '', more_link: '' },
        divider: { type: 'line', height: 20, color: '#eeeeee' },
        'image-ad': { items: [], layout: 'single', columns: 2 },
        'image-cube': {
            items: [
                { image: '', link: '', title: '' },
                { image: '', link: '', title: '' },
            ],
            layout: 'row-2',
            cols: 2,
            gap: 20,
        },
        hotzone: { image: '', areas: [] },
        'search-banner': {
            style: 'card',
            logo: '',
            logo_sticky: '',
            brand_mode: 'logo',
            brand_text: '',
            placeholder: '请输入搜索词',
            search_link: '',
            theme_color: '#ff6034',
            blur_bg: true,
            show_tabs: true,
            tabs: [
                { text: '精选', link: '' },
                { text: '新品', link: '' },
                { text: '热卖', link: '' },
            ],
            sticky: false,
            hotwords: [],
            hotword_interval: 3000,
            height: 360,
            autoplay: true,
            interval: 3000,
            indicator_style: 'dot',
            indicator_position: 'inside-bottom',
            items: [],
        },
        video: { src: '', poster: '', autoplay: false, loop: false, height: 300 },
        notice: {
            style: 'bar',
            items: [],
            speed: 3000,
            icon: '',
            scroll_mode: 'vertical',
        },
        'search-bar': { placeholder: '搜索', radius: 20, bg_color: '#f5f5f5', link: '' },
        'float-button': { items: [], position: 'right-bottom' },
        'user-info-card': { show_assets: true, assets: defaultAssets() },
        'service-menu': { items: [] }
    }
    return { ...(base[type] || {}), componentStyle: defaultComponentStyle(type) }
}

export function defaultPageSettings(): DiyPageSettings {
    return {
        background_color: '',
        background_image: '',
        show_header: true,
        popup_ad: {
            enabled: false,
            display_type: 'first',
            image: '',
            link: ''
        }
    }
}

function normalizePageSettings(settings?: Partial<DiyPageSettings> | Record<string, any> | null): DiyPageSettings {
    const base = defaultPageSettings()
    const src = settings || {}
    return {
        background_color: String(src.background_color ?? base.background_color ?? ''),
        background_image: String(src.background_image ?? base.background_image ?? ''),
        show_header: src.show_header !== false,
        popup_ad: {
            enabled: !!src.popup_ad?.enabled,
            display_type: src.popup_ad?.display_type === 'every' ? 'every' : 'first',
            image: String(src.popup_ad?.image ?? ''),
            link: String(src.popup_ad?.link ?? '')
        }
    }
}

// 模块级单例（对齐 Shop useEditor）：编辑器壳 / PropertyPanel / PageSettingsPanel 共享同一份状态
const components = ref<DiyComponent[]>([])
const pageSettings = ref<DiyPageSettings>(defaultPageSettings())
const pageTitle = ref('')
const pageSettingsActive = ref(false)
const selectedId = ref<string | null>(null)
const undoStack = ref<string[]>([])
const redoStack = ref<string[]>([])
const LIMIT = 50

export function useEditor() {
    const selected = computed(() => components.value.find((c) => c.id === selectedId.value) || null)

    function snapshot(): string {
        return JSON.stringify({
            components: components.value,
            pageSettings: pageSettings.value,
            pageTitle: pageTitle.value
        })
    }

    function pushUndo() {
        undoStack.value.push(snapshot())
        if (undoStack.value.length > LIMIT) undoStack.value.shift()
        redoStack.value = []
    }

    function apply(s: string) {
        const o = JSON.parse(s)
        components.value = o.components
        pageSettings.value = normalizePageSettings(o.pageSettings)
        pageTitle.value = String(o.pageTitle || '')
    }

    function setState(
        comps: DiyComponent[],
        settings?: Partial<DiyPageSettings> | Record<string, any> | null,
        title?: string
    ) {
        components.value = Array.isArray(comps) ? comps : []
        pageSettings.value = normalizePageSettings(settings)
        pageTitle.value = String(title ?? pageTitle.value ?? '')
        undoStack.value = []
        redoStack.value = []
        selectedId.value = null
        pageSettingsActive.value = false
    }

    function updatePageSettings(partial: Partial<DiyPageSettings>) {
        pageSettings.value = normalizePageSettings({ ...pageSettings.value, ...partial })
    }

    function activatePageSettings() {
        selectedId.value = null
        pageSettingsActive.value = true
    }

    function addWidget(type: string, presetProps?: Record<string, any>) {
        // 轮播搜索每页仅允许一个（对齐 Niushop）
        if (type === 'search-banner' && components.value.some((c) => c.type === 'search-banner')) {
            return { ok: false as const, reason: 'search-banner-exists' }
        }
        pushUndo()
        const base = presetProps ?? defaultProps(type)
        const props = { ...base, componentStyle: base.componentStyle ?? defaultComponentStyle(type) }
        const c = { id: genId(), type, props }
        // 有选中组件时插入到其后，否则追加到末尾
        const idx = selectedId.value
            ? components.value.findIndex((x) => x.id === selectedId.value)
            : -1
        if (idx >= 0) {
            components.value.splice(idx + 1, 0, c)
        } else {
            components.value.push(c)
        }
        selectedId.value = c.id
        pageSettingsActive.value = false
        return { ok: true as const }
    }

    /** 上移(dir=-1)/下移(dir=1)某组件 */
    function move(id: string, dir: -1 | 1) {
        const idx = components.value.findIndex((c) => c.id === id)
        if (idx < 0) return
        const target = idx + dir
        if (target < 0 || target >= components.value.length) return
        pushUndo()
        const arr = components.value
        const [item] = arr.splice(idx, 1)
        arr.splice(target, 0, item)
    }

    function remove(id: string) {
        pushUndo()
        components.value = components.value.filter((c) => c.id !== id)
        if (selectedId.value === id) selectedId.value = null
    }

    function duplicate(id: string) {
        const idx = components.value.findIndex((c) => c.id === id)
        if (idx === -1) return
        const source = components.value[idx]
        if (source.type === 'search-banner') {
            return { ok: false as const, reason: 'search-banner-exists' }
        }
        pushUndo()
        const copy: DiyComponent = {
            ...JSON.parse(JSON.stringify(source)),
            id: genId()
        }
        components.value.splice(idx + 1, 0, copy)
        selectedId.value = copy.id
        pageSettingsActive.value = false
        return { ok: true as const }
    }

    function toggleHidden(id: string) {
        const comp = components.value.find((c) => c.id === id)
        if (!comp) return
        pushUndo()
        comp.hidden = !comp.hidden
    }

    function reorder(ids: string[]) {
        pushUndo()
        const map = new Map(components.value.map((c) => [c.id, c]))
        components.value = ids.map((id) => map.get(id)).filter(Boolean) as DiyComponent[]
    }

    function select(id: string) {
        selectedId.value = id
        pageSettingsActive.value = false
    }

    /**
     * 在「即将修改某组件属性/样式」之前调用，捕获修改前快照用于撤销。
     * 必须在 mutation 之前（如绑定到输入控件的 @focus，而非 @change）。
     */
    function beginChange() {
        pushUndo()
    }

    function undo() {
        if (!undoStack.value.length) return
        redoStack.value.push(snapshot())
        apply(undoStack.value.pop() as string)
    }

    function redo() {
        if (!redoStack.value.length) return
        undoStack.value.push(snapshot())
        apply(redoStack.value.pop() as string)
    }

    return {
        components,
        pageSettings,
        pageTitle,
        pageSettingsActive,
        selectedId,
        selected,
        undoStack,
        redoStack,
        setState,
        updatePageSettings,
        activatePageSettings,
        addWidget,
        duplicate,
        toggleHidden,
        move,
        remove,
        reorder,
        select,
        beginChange,
        undo,
        redo
    }
}

/** 仅个人中心（member）页可用的内置组件 */
export const MEMBER_ONLY_TYPES = ['user-info-card', 'service-menu'] as const
