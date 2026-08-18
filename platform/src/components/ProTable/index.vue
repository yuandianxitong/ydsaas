<template>
    <el-card class="table-card" shadow="never">
        <div class="table-header">
            <template v-if="selectionCount === 0">
                <div class="table-title">
                    {{ title }}
                    <span class="table-count">{{
                        $t('common.totalCount', { total: pagination.total })
                    }}</span>
                </div>
                <div class="table-actions">
                    <slot name="headerExtra" />
                    <el-button v-if="onExport !== undefined" @click="handleExport">
                        {{ $t('common.export') }}
                    </el-button>
                    <el-button @click="colCfgVisible = true">
                        <i class="i-svg:sliders-horizontal" />
                        {{ $t('common.columnConfig') }}
                    </el-button>
                </div>
            </template>
            <template v-else>
                <div class="table-sel-info">
                    {{ $t('common.selectedCount', { count: selectionCount }) }}
                    <button class="table-sel-clear" @click="clearSelection">
                        {{ $t('common.clearSelection') }}
                    </button>
                </div>
                <div class="table-actions">
                    <slot
                        name="batchActions"
                        :selected-ids="selectedIds"
                        :selected-rows="selectedRows"
                        :clear-selection="clearSelection"
                    />
                    <el-button size="small" type="danger" @click="onBatchDelete">
                        <i class="i-svg:trash-2" />
                        {{ $t('common.batchDelete') }}
                    </el-button>
                </div>
            </template>
        </div>

        <el-table
            ref="tableRef"
            v-loading="loading"
            :data="data"
            :row-key="rowKey"
            :highlight-current-row="highlightCurrentRow"
            @selection-change="onSelectionChange"
            @current-change="onCurrentChange"
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

        <div v-if="showPagination" class="table-footer">
            <div class="table-footer-total">
                {{ $t('common.totalRecords', { total: pagination.total }) }}
            </div>
            <el-pagination
                :current-page="pagination.page"
                :page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="prev, pager, next, sizes"
                small
                @current-change="$emit('page-change', $event)"
                @size-change="$emit('size-change', $event)"
            />
        </div>

        <ColumnConfig
            v-model="colCfgVisible"
            :columns="columnConfigItems"
            @update:columns="onColumnConfigChange"
        />
    </el-card>
</template>

<script setup lang="ts">
import type { ElTable } from 'element-plus'
import { computed, ref } from 'vue'

import ColumnConfig from '@/components/ColumnConfig/index.vue'

import type { ProColumn } from './types'
import { useColumnConfig } from './useColumnConfig'

const props = withDefaults(
    defineProps<{
        title: string
        storageKey: string
        columns: ProColumn[]
        data: any[]
        loading?: boolean
        pagination?: { page: number; limit: number; total: number }
        batchDeleteFn?: (ids: number[]) => Promise<any>
        onExport?: () => void
        rowKey?: string
        showPagination?: boolean
        highlightCurrentRow?: boolean
    }>(),
    {
        loading: false,
        rowKey: 'id',
        batchDeleteFn: undefined,
        onExport: undefined,
        showPagination: true,
        highlightCurrentRow: false,
        pagination: () => ({ page: 1, limit: 20, total: 0 })
    }
)

const emit = defineEmits<{
    'page-change': [page: number]
    'size-change': [size: number]
    'selection-change': [rows: any[]]
    'current-change': [row: any]
}>()

const { colCfgVisible, visibleColumns, columnConfigItems, onColumnConfigChange } = useColumnConfig(
    props.storageKey,
    () => props.columns
)

const tableRef = ref<InstanceType<typeof ElTable>>()
const selectedRows = ref<any[]>([])

const selectionCount = computed(() => selectedRows.value.length)
const selectedIds = computed(() => selectedRows.value.map((r) => r[props.rowKey]))

function onSelectionChange(rows: any[]) {
    selectedRows.value = rows
    emit('selection-change', rows)
}

function onCurrentChange(row: any) {
    emit('current-change', row)
}

function clearSelection() {
    tableRef.value?.clearSelection()
}

function onBatchDelete() {
    if (!props.batchDeleteFn) return
    props.batchDeleteFn(selectedIds.value)
}

function handleExport() {
    props.onExport?.()
}
</script>
