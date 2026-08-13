<template>
    <el-card class="table-card" shadow="never">
        <!-- Table Header -->
        <div class="table-header">
            <div v-if="selectionCount === 0" class="table-title">
                {{ title }}
                <span class="table-count">{{
                    $t('component.proTable.total', { total: pagination.total })
                }}</span>
            </div>
            <div v-else class="table-sel-info">
                <i18n-t keypath="component.proTable.selected" tag="span">
                    <template #count><b>{{ selectionCount }}</b></template>
                </i18n-t>
                <button class="table-sel-clear" @click="clearSelection">
                    {{ $t('component.proTable.clearSelection') }}
                </button>
            </div>
            <div class="table-actions">
                <slot
                    name="headerExtra"
                    :selected-ids="selectedIds"
                    :selected-rows="selectedRows"
                    :selection-count="selectionCount"
                    :clear-selection="clearSelection"
                />
                <slot
                    v-if="selectionCount > 0"
                    name="batchActions"
                    :selected-ids="selectedIds"
                    :selected-rows="selectedRows"
                    :clear-selection="clearSelection"
                />
                <el-button v-if="onExport !== undefined" @click="handleExport">
                    {{ $t('component.proTable.export') }}
                </el-button>
                <el-button
                    v-if="showColumnConfig && selectionCount === 0"
                    @click="colCfgVisible = true"
                >
                    <i class="i-svg:sliders-horizontal" />
                    {{ $t('component.proTable.columnConfig') }}
                </el-button>
                <el-button
                    v-if="showBatchDelete && selectionCount > 0"
                    size="small"
                    type="danger"
                    @click="onBatchDelete"
                >
                    <i class="i-svg:trash-2" />
                    {{ $t('component.proTable.batchDelete') }}
                </el-button>
            </div>
        </div>

        <!-- Table -->
        <el-table
            ref="tableRef"
            v-loading="loading"
            :data="data"
            :row-key="rowKey"
            @selection-change="onSelectionChange"
        >
            <el-table-column v-if="batchDeleteFn !== undefined" type="selection" width="50" />
            <el-table-column
                v-for="col in visibleColumns"
                :key="col.key"
                :prop="col.prop || col.key"
                :label="col.label"
                :width="col.width"
                :min-width="col.minWidth"
                :fixed="col.fixed || undefined"
                :show-overflow-tooltip="col.showOverflowTooltip"
                :align="col.align"
                :sortable="col.sortable"
            >
                <template #default="scope">
                    <slot :name="col.key" v-bind="scope">
                        {{ scope.row[col.prop || col.key] }}
                    </slot>
                </template>
            </el-table-column>
        </el-table>

        <!-- Pagination Footer -->
        <div class="table-footer">
            <i18n-t
                keypath="component.proTable.totalRecords"
                tag="div"
                class="table-footer-total"
            >
                <template #total><b>{{ pagination.total }}</b></template>
            </i18n-t>
            <el-pagination
                :current-page="pagination.page"
                :page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                :layout="paginationLayout"
                size="small"
                @current-change="$emit('page-change', $event)"
                @size-change="$emit('size-change', $event)"
            />
        </div>

        <!-- Column Config Dialog -->
        <ColumnConfig
            v-model="colCfgVisible"
            :columns="columnConfigItems"
            @update:columns="onColumnConfigChange"
        />
    </el-card>
</template>

<script setup lang="ts">
import type { TableInstance } from 'element-plus'
import { computed, ref } from 'vue'

import ColumnConfig from './ColumnConfig.vue'
import type { ProColumn } from './types'
import { useColumnConfig } from './useColumnConfig'

defineSlots<{
    [name: string]: (props: any) => any
}>()

const props = withDefaults(
    defineProps<{
        title: string
        storageKey: string
        columns: ProColumn[]
        data: any[]
        loading?: boolean
        pagination: { page: number; limit: number; total: number }
        batchDeleteFn?: (ids: number[]) => Promise<any>
        onExport?: () => void
        rowKey?: string
        /** 是否显示列配置按钮（默认 true） */
        showColumnConfig?: boolean
        /** 是否显示自带的批量删除按钮（默认 true）。关闭后可在 batchActions 插槽中自定义批量操作。 */
        showBatchDelete?: boolean
        /** el-pagination 的 layout（默认 'prev, pager, next, sizes'，即 Shop 原布局；需要跳页器时传入含 jumper 的值） */
        paginationLayout?: string
    }>(),
    {
        loading: false,
        rowKey: 'id',
        batchDeleteFn: undefined,
        onExport: undefined,
        showColumnConfig: true,
        showBatchDelete: true,
        paginationLayout: 'prev, pager, next, sizes',
    }
)

const emit = defineEmits<{
    'page-change': [page: number]
    'size-change': [size: number]
    'selection-change': [rows: any[]]
}>()

// Column config
const { colCfgVisible, visibleColumns, columnConfigItems, onColumnConfigChange } = useColumnConfig(
    props.storageKey,
    computed(() => props.columns)
)

// Selection state
const tableRef = ref<TableInstance>()
const selectedRows = ref<any[]>([])

const selectionCount = computed(() => selectedRows.value.length)
const selectedIds = computed(() => selectedRows.value.map((r) => r[props.rowKey]))

function onSelectionChange(rows: any[]) {
    selectedRows.value = rows
    emit('selection-change', rows)
}

function clearSelection() {
    tableRef.value?.clearSelection()
}

// Batch delete
function onBatchDelete() {
    if (!props.batchDeleteFn) return
    props.batchDeleteFn(selectedIds.value)
}

// Export
function handleExport() {
    props.onExport?.()
}
</script>

<style lang="scss" scoped>
/* ProTable 选中态 + 分页底栏（移植自 Shop admin crud-layout.scss）
 * 令牌映射：--ink-700→--color-text-secondary、--ink-500→--color-text-tertiary、
 * --ink-800→--color-text-primary、--ink-400→--color-text-disabled、
 * --ink-100→--color-divider、--brand-500→--color-brand、
 * --font-num→font-variant-numeric: tabular-nums（对齐 base.scss .num 写法） */

/* 表头标题旁的计数（"共 N 条"） */
.table-title .table-count {
    font-size: 12px;
    font-weight: 400;
    color: var(--color-text-disabled);
    margin-left: 4px;
}

/* 选中信息 */
.table-sel-info {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    color: var(--color-text-secondary);

    b {
        color: var(--color-brand);
        font-variant-numeric: tabular-nums;
    }
}

.table-sel-clear {
    background: none;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: var(--color-text-tertiary);
    font-size: 12.5px;

    &:hover {
        color: var(--color-brand);
        text-decoration: underline;
    }
}

/* 分页底栏 —— 左右分栏 */
.table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-top: 1px solid var(--color-divider);
    font-size: 12.5px;
    color: var(--color-text-tertiary);
}

.table-footer-total {
    b {
        color: var(--color-text-primary);
        font-variant-numeric: tabular-nums;
    }
}
</style>
