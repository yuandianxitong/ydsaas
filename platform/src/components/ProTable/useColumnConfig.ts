import { computed, ref } from 'vue'

import type { ActiveColumn, ColumnConfigItem, ColumnStorageState, ProColumn } from './types'

const STORAGE_PREFIX = 'col-cfg:'

function readStorage(key: string): ColumnStorageState | null {
    try {
        const raw = localStorage.getItem(STORAGE_PREFIX + key)
        return raw ? JSON.parse(raw) : null
    } catch {
        return null
    }
}

function writeStorage(key: string, state: ColumnStorageState) {
    localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(state))
}

export function useColumnConfig(storageKey: string, columns: () => ProColumn[]) {
    const colCfgVisible = ref(false)
    const storageVersion = ref(0)

    const activeColumns = computed<ActiveColumn[]>(() => {
        const cols = columns()
        storageVersion.value
        const saved = readStorage(storageKey)
        if (!saved) {
            return cols.map((c) => ({
                ...c,
                visible: c.defaultVisible !== false
            }))
        }

        const colMap = new Map(cols.map((c) => [c.key, c]))
        const ordered: ActiveColumn[] = []
        const seen = new Set<string>()

        for (const key of saved.order) {
            const col = colMap.get(key)
            if (!col) continue
            seen.add(key)
            ordered.push({
                ...col,
                visible: col.required ? true : !saved.hidden.includes(key),
                fixed: saved.fixed[key] || col.fixed || false
            })
        }

        for (const col of cols) {
            if (seen.has(col.key)) continue
            ordered.push({
                ...col,
                visible: col.defaultVisible !== false
            })
        }

        return ordered
    })

    const visibleColumns = computed(() => activeColumns.value.filter((c) => c.visible))

    const columnConfigItems = computed<ColumnConfigItem[]>(() =>
        activeColumns.value.map((c) => ({
            key: c.key,
            label: c.label,
            visible: c.visible,
            fixed: c.fixed || false,
            width: typeof c.width === 'number' ? c.width : undefined,
            required: c.required
        }))
    )

    function onColumnConfigChange(items: ColumnConfigItem[]) {
        const state: ColumnStorageState = {
            order: items.map((i) => i.key),
            hidden: items.filter((i) => !i.visible).map((i) => i.key),
            fixed: {}
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
        activeColumns,
        visibleColumns,
        columnConfigItems,
        onColumnConfigChange
    }
}
