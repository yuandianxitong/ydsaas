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
