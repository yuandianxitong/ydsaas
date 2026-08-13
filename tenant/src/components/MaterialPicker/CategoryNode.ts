import type { PropType } from 'vue'
import { computed, defineComponent, h, ref } from 'vue'
import { ElDropdown, ElDropdownItem, ElDropdownMenu, ElIcon } from 'element-plus'
import { MoreFilled } from '@element-plus/icons-vue'

import type { FileCategory } from '@/types/api'

export interface CategoryNodeLabels {
    createSub: string
    rename: string
    remove: string
}

/**
 * 分类树递归节点（从 MaterialPicker 抽出，Task 7 复用）
 *
 * 显式标注 `: any`：组件在自身 setup 的渲染函数里递归引用自己（h(CategoryNode, ...)），
 * TS 无法在初始化表达式内部推断出自身类型（TS7022 循环推断），需要提前声明类型打破循环。
 */
const CategoryNode: any = defineComponent({
    name: 'CategoryNode',
    props: {
        node: { type: Object as PropType<FileCategory>, required: true },
        currentId: { type: Number, default: undefined },
        folderIcon: { type: String, required: true },
        depth: { type: Number, default: 0 },
        labels: { type: Object as PropType<CategoryNodeLabels>, required: true }
    },
    emits: ['select', 'create', 'rename', 'delete'],
    setup(props, { emit }) {
        const expanded = ref(true)
        const hasChildren = computed(() => (props.node.children?.length ?? 0) > 0)

        return () =>
            h('div', { class: 'tree-node-wrap' }, [
                h(
                    'div',
                    {
                        class: ['category-item', { active: props.currentId === props.node.id }],
                        style: { paddingLeft: `${8 + props.depth * 16}px` },
                        onClick: () => emit('select', props.node.id)
                    },
                    [
                        hasChildren.value
                            ? h(
                                  'span',
                                  {
                                      class: ['expand-icon', { expanded: expanded.value }],
                                      onClick: (e: Event) => {
                                          e.stopPropagation()
                                          expanded.value = !expanded.value
                                      }
                                  },
                                  '▶'
                              )
                            : h('span', { class: 'expand-icon placeholder' }),
                        h('img', { src: props.folderIcon, class: 'folder-icon', alt: '' }),
                        h('span', { class: 'category-name' }, props.node.name),
                        h('span', { class: 'category-count' }, String(props.node.file_count || 0)),
                        h(
                            ElDropdown,
                            {
                                trigger: 'click',
                                onClick: (e: Event) => e.stopPropagation()
                            },
                            {
                                default: () =>
                                    h(ElIcon, {
                                        class: 'more-icon',
                                        onClick: (e: Event) => e.stopPropagation()
                                    }, () => h(MoreFilled)),
                                dropdown: () =>
                                    h(ElDropdownMenu, null, () => [
                                        h(
                                            ElDropdownItem,
                                            { onClick: () => emit('create', props.node.id) },
                                            () => props.labels.createSub
                                        ),
                                        h(
                                            ElDropdownItem,
                                            { onClick: () => emit('rename', props.node.id, props.node.name) },
                                            () => props.labels.rename
                                        ),
                                        h(
                                            ElDropdownItem,
                                            { onClick: () => emit('delete', props.node.id) },
                                            () => props.labels.remove
                                        )
                                    ])
                            }
                        )
                    ]
                ),
                hasChildren.value && expanded.value
                    ? h(
                          'div',
                          { class: 'tree-children' },
                          (props.node.children as FileCategory[]).map((child) =>
                              h(CategoryNode, {
                                  key: child.id,
                                  node: child,
                                  currentId: props.currentId,
                                  folderIcon: props.folderIcon,
                                  depth: props.depth + 1,
                                  labels: props.labels,
                                  onSelect: (id: number) => emit('select', id),
                                  onCreate: (id: number) => emit('create', id),
                                  onRename: (id: number, name: string) => emit('rename', id, name),
                                  onDelete: (id: number) => emit('delete', id)
                              })
                          )
                      )
                    : null
            ])
    }
})

export default CategoryNode
