import type { App, DirectiveBinding } from 'vue'
import { watch } from 'vue'

import { useUserStore } from '@/store'

/**
 * v-entitlement 指令
 *
 * 根据当前租户的 entitlements[] 判断是否渲染元素：
 *   v-entitlement="'mall'"                          // 单个 entitlement
 *   v-entitlement="['mall', 'points-exchange']"     // 任一即可（OR 关系）
 *
 * 关闭时隐藏元素：display:none + 清空内容 + 阻断事件，
 * 不 removeChild — 否则 Vue patcher 后续 insertBefore 会拿到 null parentNode。
 */

function checkEntitlement(value: string | string[], userStore: any): boolean {
    if (!value) return true
    if (Array.isArray(value)) {
        return userStore.hasAnyEntitlement(value)
    }
    return userStore.hasEntitlement(value)
}

function removeElement(el: HTMLElement) {
    if ((el as any).__entitlementHidden) return
    ;(el as any).__entitlementHidden = true
    el.style.display = 'none'
    el.setAttribute('aria-hidden', 'true')
    el.setAttribute('tabindex', '-1')
    while (el.firstChild) el.removeChild(el.firstChild)
    const block = (e: Event) => {
        e.stopPropagation()
        e.preventDefault()
    }
    el.addEventListener('click', block, true)
    el.addEventListener('mousedown', block, true)
    el.addEventListener('keydown', block, true)
}

const entitlement = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        if (!checkEntitlement(binding.value, userStore)) {
            removeElement(el)
            return
        }

        // 监听 saas.entitlements 变化（续费/升级套餐时动态变）
        const stop = watch(
            () => userStore.saas?.entitlements,
            () => {
                if (!checkEntitlement(binding.value, userStore)) {
                    removeElement(el)
                    stop()
                }
            },
            { deep: true }
        )
        ;(el as any).__entitlementStop = stop
    },

    unmounted(el: HTMLElement) {
        const stop = (el as any).__entitlementStop
        if (typeof stop === 'function') stop()
        delete (el as any).__entitlementStop
    }
}

export function setupEntitlementDirective(app: App) {
    app.directive('entitlement', entitlement)
}

export default entitlement
