export interface SubNavRoute {
    path: string
    meta?: { title?: string; hidden?: boolean; badge?: string }
    children?: SubNavRoute[]
}
export interface SubNavItem {
    path: string
    title: string
    badge?: string
}
export interface SubNavGroup {
    title: string
    items: SubNavItem[]
}

const toItem = (r: SubNavRoute): SubNavItem => ({
    path: r.path,
    title: r.meta?.title ?? '',
    badge: r.meta?.badge
})

const visible = (r: SubNavRoute): boolean => !r.meta?.hidden

/**
 * 把顶部菜单的 children 转成「分组 + 条目」结构。
 * - 目录（有可见 children）→ 一个分组
 * - 叶子 → 收进置顶的隐式默认组（title=''）
 * - 目录但所有 children 都隐藏 → 整组丢弃（不退化成叶子条目，避免指向空路径）
 */
export function buildSubNavGroups(children: SubNavRoute[]): SubNavGroup[] {
    const loose: SubNavItem[] = []
    const groups: SubNavGroup[] = []

    for (const child of children.filter(visible)) {
        const raw = child.children ?? []
        const sub = raw.filter(visible)
        if (sub.length) {
            groups.push({ title: child.meta?.title ?? '', items: sub.map(toItem) })
        } else if (raw.length === 0) {
            // 真正的叶子才进默认组；目录（raw 非空）若无可见子项则整组丢弃
            loose.push(toItem(child))
        }
    }

    return loose.length ? [{ title: '', items: loose }, ...groups] : groups
}
