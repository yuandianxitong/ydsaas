<script lang="ts">
import * as ElementPlusIcons from '@element-plus/icons-vue'
import { ElIcon } from 'element-plus'
import { createVNode, defineComponent, h } from 'vue'

export const EL_ICON_PREFIX = 'el-icon-'
export const LOCAL_ICON_PREFIX = 'i-svg:'

// 构建 ElementPlus 图标的映射表：前缀名 => 组件
const elIconMap: Record<string, any> = {}
Object.values(ElementPlusIcons).forEach((comp: any) => {
    elIconMap[`${EL_ICON_PREFIX}${comp.name}`] = comp
})

export default defineComponent({
    name: 'Icon',
    inheritAttrs: false,
    props: {
        name: { type: String, required: true },
        size: { type: [String, Number], default: '1em' },
        color: { type: String, default: 'inherit' }
    },
    setup(props, { attrs }) {
        return () => {
            const { name, size, color } = props
            if (typeof name !== 'string' || !name) return null

            const style = {
                fontSize: typeof size === 'number' ? `${size}px` : size,
                color
            }

            // 本地 SVG / UnoCSS 图标：与 Shop 一样直接渲 <i>，避免 ElIcon 外包撑开间距
            if (name.startsWith(LOCAL_ICON_PREFIX) || name.startsWith('i-')) {
                return h('i', { ...attrs, class: [name, attrs.class], style })
            }

            const ElComp = elIconMap[name]
            if (ElComp) {
                return createVNode(
                    ElIcon,
                    { size, color, ...attrs },
                    { default: () => createVNode(ElComp) }
                )
            }

            return h('i', { ...attrs, class: [name, attrs.class], style })
        }
    }
})
</script>
