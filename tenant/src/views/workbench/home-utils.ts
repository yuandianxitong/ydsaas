import type { PlanInfo, SubscriptionInfo } from '@/types/api'

/** 剩余天数（向上取整，最小 0）。endsAt 为空或非法返回 0。 */
export function daysRemaining(endsAt: string, now: Date = new Date()): number {
    if (!endsAt) return 0
    const end = new Date(endsAt).getTime()
    if (Number.isNaN(end)) return 0
    const diff = end - now.getTime()
    if (diff <= 0) return 0
    return Math.ceil(diff / 86_400_000)
}

export interface SubscriptionView {
    planName: string
    endsAt: string
    daysLeft: number
    isMock: boolean
}

/** 把订阅 + 套餐列表映射成首页订阅卡所需视图；无订阅时回退占位并标记 mock。 */
export function subscriptionView(
    sub: SubscriptionInfo | null,
    plans: PlanInfo[],
    now: Date = new Date()
): SubscriptionView {
    if (!sub) {
        return { planName: '未订阅', endsAt: '', daysLeft: 0, isMock: true }
    }
    const plan = plans.find((p) => p.id === sub.plan_id)
    return {
        planName: plan?.name ?? `套餐 #${sub.plan_id}`,
        endsAt: sub.ends_at,
        daysLeft: daysRemaining(sub.ends_at, now),
        isMock: false
    }
}

/** 把字节数格式化为人类可读字符串（B / KB / MB / GB / TB）。 */
export function formatBytes(bytes: number): string {
    if (!bytes || bytes <= 0) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))
    const val = bytes / 1024 ** i
    // 整数不带小数，其余保留 1 位
    return `${Number.isInteger(val) ? val : val.toFixed(1)} ${units[i]}`
}
