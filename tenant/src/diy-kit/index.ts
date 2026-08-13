/**
 * DIY 渲染器插件开发契约（tenant 编辑器预览端 facade）。
 *
 * 这是插件预览渲染器（server/plugins/<code>/tenant/components/diy/*.vue，经 sync-plugins.mjs
 * 软链到 @/components/plugins/<code>/diy/）唯一允许 import 的核心模块；@/views/diy/** 等
 * 编辑器内部实现一律不稳定，插件不得直接依赖。新增导出项须经 code review 确认为稳定契约。
 *
 * 与 C 端 uniapp/src/components/diy/pluginWidget.ts 是同一契约的两端实现：
 * item 字段形态与后端 core/diy/NormalizedWidget.php 基座白名单保持一致；
 * RENDERS 仅含核心 5 种通用形态，插件自定义 render 由宿主按注册表（widget type →
 * 插件渲染器组件）解析，不经 resolveRender。
 */
export interface PwItem {
    title: string
    desc?: string
    meta?: string
    image?: string
    link?: string
    /** 角标/标签文案（商品组等） */
    badge?: string
    /** 领取/进度类 render（coupon-row/seckill-row）使用；商品组为 goods_id */
    value?: number | string
    received?: boolean
    progress?: number
    sales?: number
}
export interface PwTab {
    label: string
    value: number | string
}
/** entry-grid render（member 专属）四宫格项 */
export interface PwEntryItem {
    icon?: string
    label: string
    link?: string
    badge_key?: string
}
/** asset-card render（member 专属）资产格项：数值为端上登录态数据，编辑器恒显示占位 */
export interface PwAssetItem {
    label: string
    link?: string
}

/** 核心通用 render（与后端 NormalizedWidget::CORE_RENDER_KINDS 一致） */
const RENDERS = ['card-list', 'list', 'single', 'grid-3', 'scroll-x']

export function resolveRender(r: unknown): string {
    return typeof r === 'string' && RENDERS.includes(r) ? r : 'list'
}

/** show_price === false 时剥离 desc（与 C 端渲染器同一职责裁剪） */
export function applyShowPrice(items: PwItem[], showPrice: unknown): PwItem[] {
    if (showPrice !== false) return items
    return items.map(({ desc: _drop, ...rest }) => rest)
}

/**
 * 倒计时文案：'HH:MM:SS'，end 无效或已过期 → ''。
 * '-' 先替换为 '/'（Safari 对 'YYYY-MM-DD HH:mm:ss' 解析为 Invalid Date）。
 */
export function countdownText(endTime: unknown, nowMs: number): string {
    if (typeof endTime !== 'string' || endTime === '') return ''
    const end = new Date(endTime.replace(/-/g, '/')).getTime()
    if (Number.isNaN(end)) return ''
    const diff = end - nowMs
    if (diff <= 0) return ''
    const totalSec = Math.floor(diff / 1000)
    const h = Math.floor(totalSec / 3600)
    const m = Math.floor((totalSec % 3600) / 60)
    const s = totalSec % 60
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${pad(h)}:${pad(m)}:${pad(s)}`
}

/**
 * 判断注水后的 props 是否有可渲染内容（无内容 → 编辑器改用示例数据渲染）。
 * 通用判定只看 items；插件专属的空态判定（如 tab-goods 仅有 tabs 也可渲染）
 * 由插件预览渲染器自行处理。
 */
export function hasRenderableContent(props: Record<string, any> | null): boolean {
    if (!props) return false
    const items = Array.isArray(props.items) ? props.items : []
    return items.length > 0
}

/**
 * 空数据时的核心通用示例 props（真实感占位卡）。仅覆盖核心 5 种 render；
 * 插件自定义 render 的示例数据由插件预览渲染器自带（协议 v1 起 domain 知识归插件）。
 */
export function sampleWidgetProps(render: string): Record<string, any> {
    const goods = (n: number): PwItem[] =>
        Array.from({ length: n }, () => ({ title: '商品名称', desc: '¥99.00' }))
    switch (render) {
        case 'single':
            return { render, items: goods(1) }
        case 'grid-3':
            return { render, items: goods(6) }
        case 'scroll-x':
            return { render, items: goods(4) }
        case 'list':
            return { render, items: goods(3) }
        default:
            return { render: 'card-list', items: goods(4) }
    }
}
