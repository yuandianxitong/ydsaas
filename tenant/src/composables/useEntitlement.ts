import { computed, type ComputedRef } from 'vue'

import useUserStore from '@/store/modules/user.store'
import type { Entitlement } from '@/types/api'

const EXPIRING_SOON_DAYS = 7

export interface EntitlementSnapshot {
    /** 是否拥有该权益 */
    has: ComputedRef<boolean>
    /** 完整 Entitlement 富对象（不存在时为 null） */
    entitlement: ComputedRef<Entitlement | null>
    /** 过期时间字符串（不存在或永不过期为 null） */
    expiresAt: ComputedRef<string | null>
    /** 距离过期的天数；null 表示永不过期或未授权 */
    daysUntilExpiry: ComputedRef<number | null>
    /** <7 天但 >=0 天 */
    expiringSoon: ComputedRef<boolean>
    /** <0 天 */
    expired: ComputedRef<boolean>
}

/**
 * 单个权益的响应式访问器。
 *
 * @example
 *   const mall = useEntitlement('mall')
 *   if (mall.has.value) ...
 *   if (mall.expiringSoon.value) ...
 */
export function useEntitlement(code: string): EntitlementSnapshot {
    const userStore = useUserStore()
    const entitlement = computed(() => userStore.getEntitlement(code))
    const has = computed(() => entitlement.value !== null)
    const expiresAt = computed(() => entitlement.value?.expires_at ?? null)
    const daysUntilExpiry = computed(() => {
        if (!expiresAt.value) return null
        const diff = new Date(expiresAt.value).getTime() - Date.now()
        return Math.floor(diff / (1000 * 60 * 60 * 24))
    })
    const expiringSoon = computed(() => {
        const d = daysUntilExpiry.value
        return d !== null && d >= 0 && d < EXPIRING_SOON_DAYS
    })
    const expired = computed(() => {
        const d = daysUntilExpiry.value
        return d !== null && d < 0
    })

    return { has, entitlement, expiresAt, daysUntilExpiry, expiringSoon, expired }
}
