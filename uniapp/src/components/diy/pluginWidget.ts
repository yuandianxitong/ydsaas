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

/** entry-grid render（shop.order-entries 等 member 专属 widget）四宫格项：UnoCSS 图标类（i-ri-*，须在 uno safelist）+ label + link + 角标 key */
export interface PwEntryItem {
  icon?: string
  label: string
  link?: string
  badge_key?: string
}
/** asset-card render（shop.asset-card 等 member 专属 widget）资产格项：label + link，数值由端上运行时拉 */
export interface PwAssetItem {
  label: string
  link?: string
  /** member_stats 注册表统计键（全限定如 shop.balance），驱动端上按键取值渲染 */
  stat_key?: string
}

/**
 * DIY 渲染器插件开发契约（C 端 facade）：本文件与 ./navigate.ts 是插件 DIY 渲染器
 * （server/plugins/<code>/uniapp/components/diy/*.vue，经 sync-plugin-uniapp.mjs 软链到
 * @/components/diy/plugins/<code>/）允许 import 的稳定 API；另可用全局公共基础设施
 * @/utils/request、@/store/user.store、@/api/user。新增导出项须经 code review 确认。
 *
 * RENDERS 仅含核心 5 种通用形态（与后端 NormalizedWidget::CORE_RENDER_KINDS 一致）；
 * 插件自定义 render 由宿主按静态注册表（widget type → 组件）解析，不经 resolveRender。
 */
const RENDERS = ['card-list', 'list', 'single', 'grid-3', 'scroll-x']

export function resolveRender(r: unknown): string {
  return typeof r === 'string' && RENDERS.includes(r) ? r : 'list'
}

export function buildFetchUrl(tpl: unknown, value: number | string): string {
  if (typeof tpl !== 'string' || tpl === '') return ''
  return tpl.replace('{value}', encodeURIComponent(String(value)))
}

/**
 * 倒计时文案：'HH:MM:SS'，end 无效或已过期 → ''。h 两位起，超 99 小时自动进三位。
 * 日期解析防护：字符串先把 '-' 替换成 '/' 再 new Date（Safari 对 'YYYY-MM-DD HH:mm:ss' 空格分隔格式解析为 Invalid Date）。
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

/** show_price === false 时剥离 desc（对注入与拉取的 items 一律生效；端点恒返回 desc，显隐是渲染器职责） */
export function applyShowPrice(items: PwItem[], showPrice: unknown): PwItem[] {
  if (showPrice !== false) return items
  return items.map(({ desc: _drop, ...rest }) => rest)
}
