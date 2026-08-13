export interface ComponentStyle {
  margin?: { top: number; right: number; bottom: number; left: number }
  padding?: { top: number; right: number; bottom: number; left: number }
  background?: {
    type?: 'color' | 'gradient' | 'image'
    color?: string
    gradientStart?: string
    gradientEnd?: string
    gradientDirection?: string
    image?: string
  }
  borderRadius?: { topLeft: number; topRight: number; bottomRight: number; bottomLeft: number }
  boxShadow?: { x: number; y: number; blur: number; color: string }
  border?: { width: number; color: string; style: 'solid' | 'dashed' | 'dotted' | 'none' }
  opacity?: number
}

// 用户决策（第八轮走查）：大多数组件默认白底是常态；分割线（divider）本质是留白间隔，
// 悬浮按钮（float-button）容器默认白底会在画布/端上出现突兀白方块——两者例外保持透明。
// 公告（notice）由皮肤自身着色，外层白底会盖住经典黄条/深色条，故透明。
// 仅影响「新拖入组件」的默认值，存量已保存数据不受影响（不做迁移/回填）。
const TRANSPARENT_BG_TYPES = new Set(['divider', 'float-button', 'notice'])

export function defaultComponentStyle(type?: string) {
  // 图片魔方 / 秒杀组默认内边距 10，避免贴边；其它组件保持 0
  const pad = type === 'image-cube' || type === 'shop.seckill-row' ? 10 : 0
  return {
    margin: { top: 0, right: 0, bottom: 0, left: 0 },
    padding: { top: pad, right: pad, bottom: pad, left: pad },
    background: {
      type: 'color' as const,
      color: type && TRANSPARENT_BG_TYPES.has(type) ? '' : '#ffffff',
      gradientStart: '#ffffff',
      gradientEnd: '#000000',
      gradientDirection: 'to bottom',
      image: '',
    },
    borderRadius: { topLeft: 0, topRight: 0, bottomRight: 0, bottomLeft: 0 },
    boxShadow: { x: 0, y: 0, blur: 0, color: 'rgba(0,0,0,0.1)' },
    border: {
      width: 0,
      color: '#e0e0e0',
      style: 'solid' as NonNullable<ComponentStyle['border']>['style'],
    },
    opacity: 100,
  }
}

export function componentStyleToCss(style?: ComponentStyle): Record<string, string> {
  const css: Record<string, string> = {}
  if (!style) return css
  const m = style.margin
  if (m) css.margin = `${m.top}px ${m.right}px ${m.bottom}px ${m.left}px`
  const p = style.padding
  if (p) css.padding = `${p.top}px ${p.right}px ${p.bottom}px ${p.left}px`

  const bg = style.background
  if (bg) {
    if (bg.type === 'gradient' && bg.gradientStart && bg.gradientEnd) {
      const dir = bg.gradientDirection || 'to bottom'
      css.background = `linear-gradient(${dir}, ${bg.gradientStart}, ${bg.gradientEnd})`
    } else if (bg.type === 'image' && bg.image) {
      css.backgroundImage = `url("${bg.image}")`
      css.backgroundSize = 'cover'
      css.backgroundPosition = 'center'
    } else if (bg.color) {
      css.backgroundColor = bg.color
    }
  }

  const r = style.borderRadius
  if (r) css.borderRadius = `${r.topLeft}px ${r.topRight}px ${r.bottomRight}px ${r.bottomLeft}px`

  const sh = style.boxShadow
  if (sh && (sh.x !== 0 || sh.y !== 0 || sh.blur !== 0)) css.boxShadow = `${sh.x}px ${sh.y}px ${sh.blur}px ${sh.color}`

  const bd = style.border
  if (bd && bd.width > 0 && bd.style !== 'none') css.border = `${bd.width}px ${bd.style} ${bd.color}`

  if (style.opacity !== undefined && style.opacity < 100) css.opacity = String(style.opacity / 100)

  return css
}

export default componentStyleToCss
