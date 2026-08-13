import type { App, DirectiveBinding } from 'vue'
import { watch } from 'vue'

import { useUserStore } from '@/store'

/**
 * v-feature 指令
 *
 * 根据当前租户的 entitlements[] 判断是否渲染元素：
 *   v-feature="'mall'"
 *   v-feature="['mall', 'points-exchange']"
 *
 * 关闭时隐藏元素：display:none + 清空内容 + 阻断事件。
 *
 * @deprecated 改用 v-entitlement —— v-feature 现在内部完全 delegate 到 hasEntitlement。
 *   保留以兼容存量调用点；下一个 major 版本会移除。
 */

function checkFeature(value: string | string[], userStore: any): boolean {
    if (!value) return true
    if (Array.isArray(value)) {
        return userStore.hasAnyFeature(value)
    }
    return userStore.hasFeature(value)
}

function removeElement(el: HTMLElement) {
    if ((el as any).__featureHidden) return
    ;(el as any).__featureHidden = true
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

const feature = {
    mounted(el: HTMLElement, binding: DirectiveBinding) {
        const userStore = useUserStore()
        if (!checkFeature(binding.value, userStore)) {
            removeElement(el)
            return
        }

        // 监听 saas.entitlements 变化（租户续费/升级套餐时可能动态变）
        const stop = watch(
            () => userStore.saas?.entitlements,
            () => {
                if (!checkFeature(binding.value, userStore)) {
                    removeElement(el)
                    stop()
                }
            },
            { deep: true }
        )
        ;(el as any).__featureStop = stop
    },

    unmounted(el: HTMLElement) {
        const stop = (el as any).__featureStop
        if (typeof stop === 'function') stop()
        delete (el as any).__featureStop
    }
}

export function setupFeatureDirective(app: App) {
    app.directive('feature', feature)
}

export default feature
