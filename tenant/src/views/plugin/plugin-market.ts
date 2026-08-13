import type { TenantPluginInfo } from '@/api/plugin'

export interface MarketCategory {
    key: string
    label: string
    items: TenantPluginInfo[]
}

// 后端 config/plugin.php 的 categories 镜像（新增分类需两边同步）
export const CATEGORY_LABELS: Record<string, string> = {
    business: '业务应用',
    marketing: '营销玩法',
    channel: '渠道对接',
    data: '数据工具',
    utility: '辅助工具',
    other: '其他'
}
const CATEGORY_ORDER: Record<string, number> = {
    business: 1,
    marketing: 2,
    channel: 3,
    data: 4,
    utility: 5,
    other: 99
}

export function groupByCategory(list: TenantPluginInfo[]): MarketCategory[] {
    const buckets = new Map<string, TenantPluginInfo[]>()
    for (const p of list) {
        const key = CATEGORY_LABELS[p.category] ? p.category : 'other'
        let bucket = buckets.get(key)
        if (!bucket) {
            bucket = []
            buckets.set(key, bucket)
        }
        bucket.push(p)
    }
    return [...buckets.entries()]
        .map(([key, items]) => ({ key, label: CATEGORY_LABELS[key] ?? '其他', items }))
        .sort((a, b) => (CATEGORY_ORDER[a.key] ?? 50) - (CATEGORY_ORDER[b.key] ?? 50))
}
