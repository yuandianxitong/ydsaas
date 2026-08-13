import { CUBE_LAYOUTS } from './cubeLayouts'
import type { ComponentStyle } from './styleUtils'

/** 样式预设选项（插件 props_schema type=style-preset / 内置组件共用） */
export interface StylePresetOption {
    label: string
    value: string
    /** 缩略图 URL；缺省时 Picker 用 CSS 迷你预览 */
    thumb?: string
    patch?: {
        props?: Record<string, unknown>
        componentStyle?: Partial<ComponentStyle>
    }
}

function mergeComponentStyle(
    base: ComponentStyle | undefined,
    patch: Partial<ComponentStyle>
): ComponentStyle {
    const next: ComponentStyle = { ...(base || {}) }
    for (const [k, v] of Object.entries(patch) as Array<[keyof ComponentStyle, unknown]>) {
        if (v && typeof v === 'object' && !Array.isArray(v)) {
            next[k] = { ...((next[k] as object) || {}), ...(v as object) } as never
        } else {
            next[k] = v as never
        }
    }
    return next
}

/**
 * 切换样式预设：写入 props[styleKey]，并按 option.patch 合并声明过的键。
 * 仅覆盖 patch 中出现的字段，避免冲掉用户其它细调。
 */
export function applyStylePreset(
    props: Record<string, any>,
    option: StylePresetOption,
    styleKey = 'style'
): void {
    props[styleKey] = option.value
    const patch = option.patch
    if (!patch) return
    if (patch.props) {
        Object.assign(props, patch.props)
    }
    if (patch.componentStyle) {
        props.componentStyle = mergeComponentStyle(props.componentStyle, patch.componentStyle)
    }
}

/** 图片魔方：11 种固定拼版 */
export const CUBE_LAYOUT_PRESETS: StylePresetOption[] = CUBE_LAYOUTS.map((l) => ({
    label: l.label,
    value: l.id,
    patch: {
        props: { layout: l.id, cols: l.columns },
    },
}))

/** 轮播搜索：轮播呈现风格（顶栏统一叠在模糊底上） */
export const SEARCH_BANNER_STYLE_PRESETS: StylePresetOption[] = [
    {
        label: '样式一',
        value: 'card',
        patch: { props: { style: 'card' } },
    },
    {
        label: '样式二',
        value: 'peek',
        patch: { props: { style: 'peek' } },
    },
]

/** 兼容一期旧值 → 新风格 */
export function normalizeSearchBannerStyle(raw?: string): 'card' | 'peek' {
    const v = String(raw || 'card')
    if (v === 'peek' || v === 'stacked') return 'peek'
    return 'card' // card | overlay | 其它
}

/** 公告：排版预设（非单纯换色） */
export const NOTICE_STYLE_PRESETS: StylePresetOption[] = [
    {
        label: '通栏条',
        value: 'bar',
        patch: {
            props: { scroll_mode: 'vertical' },
            componentStyle: {
                background: { type: 'color', color: '#fffbe6' },
                borderRadius: { topLeft: 0, topRight: 0, bottomRight: 0, bottomLeft: 0 },
                padding: { top: 8, right: 12, bottom: 8, left: 12 },
            },
        },
    },
    {
        label: '资讯列表',
        value: 'news',
        patch: {
            props: { scroll_mode: 'vertical' },
            componentStyle: {
                background: { type: 'color', color: '#ffffff' },
                borderRadius: { topLeft: 0, topRight: 0, bottomRight: 0, bottomLeft: 0 },
                padding: { top: 10, right: 12, bottom: 10, left: 12 },
            },
        },
    },
    {
        label: '跑马灯卡',
        value: 'marquee-card',
        patch: {
            props: { scroll_mode: 'marquee' },
            componentStyle: {
                background: { type: 'color', color: '#ffffff' },
                borderRadius: { topLeft: 0, topRight: 0, bottomRight: 0, bottomLeft: 0 },
                padding: { top: 10, right: 12, bottom: 10, left: 12 },
                boxShadow: { x: 0, y: 2, blur: 8, color: 'rgba(23,32,51,0.06)' },
            },
        },
    },
]
