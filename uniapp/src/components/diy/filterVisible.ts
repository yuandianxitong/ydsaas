export interface VisibleFilterable {
  hidden?: boolean
  props?: Record<string, any>
  [key: string]: any
}

function currentPlatform(): string {
  // #ifdef H5
  return 'h5'
  // #endif
  // #ifdef MP-WEIXIN
  return 'mp-weixin'
  // #endif
  // #ifdef APP-PLUS
  return 'app'
  // #endif
  return 'h5'
}

function inSchedule(props?: Record<string, any> | null): boolean {
  if (!props) return true
  const start = String(props.schedule_start || '').trim()
  const end = String(props.schedule_end || '').trim()
  if (!start && !end) return true
  const now = Date.now()
  if (start) {
    const t = new Date(start.replace(/-/g, '/')).getTime()
    if (!Number.isNaN(t) && now < t) return false
  }
  if (end) {
    const t = new Date(end.replace(/-/g, '/')).getTime()
    if (!Number.isNaN(t) && now > t) return false
  }
  return true
}

function platformAllowed(props?: Record<string, any> | null): boolean {
  const platforms = props?.platforms
  if (!Array.isArray(platforms) || platforms.length === 0) return true
  const cur = currentPlatform()
  return platforms.map(String).includes(cur)
}

/** 过滤 hidden / 定时 / 生效端；对空值/非数组容错，返回空数组。 */
export const filterVisible = <T extends VisibleFilterable>(list?: T[] | null): T[] =>
  (list || []).filter((c) => !c.hidden && inSchedule(c.props) && platformAllowed(c.props))
