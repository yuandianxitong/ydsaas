// src/directives/index.ts
import type { App } from 'vue'

import copy from './copy'
import { setupEntitlementDirective } from './entitlement'
import { setupFeatureDirective } from './feature'
import permsDirective from './perms'

export function installDirectives(app: App) {
    // 安装权限指令
    app.use(permsDirective)

    // 安装复制指令
    app.directive('copy', copy)

    // 安装 SaaS 功能开关指令（v-feature 保留兼容；v-entitlement 是新 API）
    setupFeatureDirective(app)
    setupEntitlementDirective(app)
}
