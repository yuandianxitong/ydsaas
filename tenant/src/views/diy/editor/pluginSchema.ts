import type { PluginWidgetField } from '@/api/diy'

/** show_if 过滤：无 show_if 恒显示；隐藏字段的值保留不清空（hydrator 只读当前 source 对应字段，脏值无害） */
export function visibleFields(
    schema: PluginWidgetField[],
    props: Record<string, any>
): PluginWidgetField[] {
    return schema.filter((f) => !f.show_if || props[f.show_if.key] === f.show_if.value)
}

/** 按 section 分组可见字段（连续同名 section 合并；无 section 单独一组） */
export function groupVisibleFields(
    schema: PluginWidgetField[],
    props: Record<string, any>
): Array<{ section: string; fields: PluginWidgetField[] }> {
    const visible = visibleFields(schema, props)
    const groups: Array<{ section: string; fields: PluginWidgetField[] }> = []
    for (const f of visible) {
        const section = String(f.section || '').trim()
        const last = groups[groups.length - 1]
        if (last && last.section === section) {
            last.fields.push(f)
        } else {
            groups.push({ section, fields: [f] })
        }
    }
    return groups
}
