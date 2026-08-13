import type { App } from 'vue'

/**
 * highlight.js 体积大且当前几乎无消费方；挂载时再动态加载，避免打进首屏包。
 */
export function installHighlight(app: App) {
    app.directive('highlight', {
        async mounted(el) {
            const { default: hljs } = await import('highlight.js')
            const blocks = el.querySelectorAll('pre code')
            blocks.forEach((block: Element) => hljs.highlightElement(block as HTMLElement))
        }
    })
}
