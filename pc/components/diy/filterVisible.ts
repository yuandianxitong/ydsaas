export interface VisibleFilterable {
  hidden?: boolean
  props?: Record<string, any>
  [key: string]: any
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
  // PC 门户按 h5 处理
  return platforms.map(String).includes('h5')
}

export const filterVisible = <T extends VisibleFilterable>(list?: T[] | null): T[] =>
  (list || []).filter((c) => !c.hidden && inSchedule(c.props) && platformAllowed(c.props))
