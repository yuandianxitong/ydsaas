import { ElLoading } from 'element-plus'
import type { App } from 'vue'

/**
 * 仅注册首屏必需的指令/服务；组件与 API（ElMessage 等）走
 * unplugin-vue-components / unplugin-auto-import 的 ElementPlusResolver，按需入包。
 */
export function installElementPlus(app: App) {
    app.use(ElLoading) // v-loading
}
