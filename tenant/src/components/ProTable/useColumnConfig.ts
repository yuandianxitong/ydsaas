import { computed, ref, unref } from 'vue'
import type { Ref } from 'vue'

import cache from '@/utils/cache'

import type { ActiveColumn, ColumnConfigItem, ColumnStorageState, ProColumn } from './types'

const STORAGE_PREFIX = 'col_cfg_'

function readStorage(storageKey: string): ColumnStorageState | null {
    const v = cache.get(STORAGE_PREFIX + storageKey)
    return v && typeof v === 'object' ? (v as ColumnStorageState) : null
}

function writeStorage(storageKey: string, state: ColumnStorageState): void {
    cache.set(STORAGE_PREFIX + storageKey, state)
}

export function useColumnConfig(storageKey: string, columns: ProColumn[] | Ref<ProColumn[]>) {
    const colCfgVisible = ref(false)
    // 保存后递增以触发 activeColumns 重新计算（cache 非响应式）
    const storageVersion = ref(0)

    /** Merge ProColumn[] with cache state → ordered ActiveColumn[] */
    const activeColumns = computed<ActiveColumn[]>(() => {
        const cols = unref(columns)
        // 依赖 storageVersion 让保存触发重算
        storageVersion.value
        const saved = readStorage(storageKey)
        if (!saved) {
            return cols.map((c) => ({
                ...c,
                visible: c.defaultVisible !== false,
            }))
        }

        // Build a map of all columns by key
        const colMap = new Map(cols.map((c) => [c.key, c]))

        // Start with saved order (skip keys that no longer exist)
        const ordered: ActiveColumn[] = []
        const seen = new Set<string>()

        for (const key of saved.order) {
            const col = colMap.get(key)
            if (!col) continue
            seen.add(key)
            ordered.push({
                ...col,
                visible: col.required ? true : !saved.hidden.includes(key),
                fixed: saved.fixed[key] || col.fixed || false,
            })
        }

        // Append any new columns not in saved order
        for (const col of cols) {
            if (seen.has(col.key)) continue
            ordered.push({
                ...col,
                visible: col.defaultVisible !== false,
            })
        }

        return ordered
    })

    /** Only visible columns for rendering */
    const visibleColumns = computed(() => activeColumns.value.filter((c) => c.visible))

    /** Convert to ColumnConfigItem[] for ColumnConfig dialog */
    const columnConfigItems = computed<ColumnConfigItem[]>(() =>
        activeColumns.value.map((c) => ({
            key: c.key,
            label: c.label,
            visible: c.visible,
            fixed: c.fixed || false,
            width: typeof c.width === 'number' ? c.width : undefined,
            required: c.required,
        }))
    )

    /** Handle ColumnConfig dialog confirm */
    function onColumnConfigChange(items: ColumnConfigItem[]) {
        const state: ColumnStorageState = {
            order: items.map((i) => i.key),
            hidden: items.filter((i) => !i.visible).map((i) => i.key),
            fixed: {},
        }
        for (const item of items) {
            if (item.fixed) {
                state.fixed[item.key] = item.fixed
            }
        }
        writeStorage(storageKey, state)
        storageVersion.value++
    }

    return {
        colCfgVisible,
        visibleColumns,
        columnConfigItems,
        onColumnConfigChange,
    }
}
