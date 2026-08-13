import type { PluginWidgetField, PluginWidgetMeta } from '@/api/diy'

/** 面板取值：props 缺省时回退 default_props，避免 switch/预设显示未选 */
export function resolveProp(
    props: Record<string, any> | null | undefined,
    key: string,
    defaultProps?: Record<string, any> | null
): any {
    if (props && Object.prototype.hasOwnProperty.call(props, key) && props[key] !== undefined) {
        return props[key]
    }
    if (defaultProps && Object.prototype.hasOwnProperty.call(defaultProps, key)) {
        return defaultProps[key]
    }
    return undefined
}

/**
 * 把 meta.default_props / 内置 defaults 中缺失键写回组件 props。
 * 不覆盖用户已显式设置的值（含 false/0/''）。
 */
export function ensureDefaultProps(
    component: { type: string; props: Record<string, any> } | null | undefined,
    defaultProps?: Record<string, any> | null
): void {
    if (!component?.props || !defaultProps) return
    for (const [k, v] of Object.entries(defaultProps)) {
        if (k === 'componentStyle') continue
        if (!Object.prototype.hasOwnProperty.call(component.props, k) || component.props[k] === undefined) {
            component.props[k] = v
        }
    }
}

/** checkbox-group：根据 options[].value（show_* 键）收集当前勾选列表 */
export function checkboxGroupValue(
    props: Record<string, any>,
    field: PluginWidgetField,
    defaultProps?: Record<string, any> | null
): string[] {
    const keys = (field.options || []).map((o) => String(o.value))
    return keys.filter((k) => {
        const v = resolveProp(props, k, defaultProps)
        return v !== false && v !== 0 && v !== '0'
    })
}

/** checkbox-group 变更：把勾选映射回多个布尔 props */
export function applyCheckboxGroup(
    props: Record<string, any>,
    field: PluginWidgetField,
    selected: string[]
): void {
    const set = new Set(selected.map(String))
    for (const o of field.options || []) {
        const k = String(o.value)
        props[k] = set.has(k)
    }
}

export function defaultPropsOfMeta(meta: PluginWidgetMeta | null): Record<string, any> {
    return (meta?.default_props || {}) as Record<string, any>
}
