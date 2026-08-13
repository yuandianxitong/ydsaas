import type { TenantPluginInfo } from '@/api/plugin'

/** 即将到期阈值（天） */
export const EXPIRING_DAYS = 30

/** 已安装：已启用(1) 或 已禁用(2) */
export function isInstalled(p: TenantPluginInfo): boolean {
    return p.tenant_status === 1 || p.tenant_status === 2
}

/** 可用：套餐授权但未启用(0) */
export function isAvailable(p: TenantPluginInfo): boolean {
    return p.tenant_status === 0
}

/** 即将到期：已过期(status 3)，或 expired_at 在 EXPIRING_DAYS 天内（含已过期） */
export function isExpiring(p: TenantPluginInfo, now: Date = new Date()): boolean {
    if (p.tenant_status === 3) return true
    if (!p.expired_at) return false
    // Safari 兼容：把 'YYYY-MM-DD HH:mm:ss' 的 '-' 换成 '/'
    const end = new Date(p.expired_at.replace(/-/g, '/')).getTime()
    if (Number.isNaN(end)) return false
    const days = (end - now.getTime()) / 86_400_000
    return days <= EXPIRING_DAYS
}

export function filterInstalled(list: TenantPluginInfo[]): TenantPluginInfo[] {
    return list.filter(isInstalled)
}
export function filterAvailable(list: TenantPluginInfo[]): TenantPluginInfo[] {
    return list.filter(isAvailable)
}
export function filterExpiring(list: TenantPluginInfo[], now?: Date): TenantPluginInfo[] {
    return list.filter((p) => isExpiring(p, now))
}
