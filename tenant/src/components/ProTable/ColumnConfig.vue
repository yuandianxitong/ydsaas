<template>
    <el-dialog
        :model-value="modelValue"
        title=""
        :show-close="false"
        :close-on-click-modal="true"
        append-to-body
        class="col-cfg-dialog dlg-md"
        @update:model-value="emit('update:modelValue', $event)"
        @close="handleClose"
    >
        <template #header>
            <div class="modal-head">
                <div class="modal-title">
                    {{ $t('component.columnConfig.title') }}
                    <span class="sub">{{ $t('component.columnConfig.subtitle') }}</span>
                </div>
                <button class="modal-close" @click="handleClose">
                    <i class="i-svg:x" />
                </button>
            </div>
        </template>

        <div class="col-cfg-head">
            <label class="col-cfg-all">
                <input type="checkbox" :checked="allChecked" @change="toggleAll" />
                <span>{{ $t('component.columnConfig.checkAll') }}</span>
            </label>
            <div class="col-cfg-stat">
                {{
                    $t('component.columnConfig.shown', {
                        shown: checkedCount,
                        total: localColumns.length,
                    })
                }}
            </div>
        </div>

        <div class="col-cfg-list">
            <div
                v-for="(col, index) in localColumns"
                :key="col.key"
                class="col-cfg-row"
                :class="{
                    dragging: dragIndex === index,
                    over: overIndex === index && dragIndex !== null && dragIndex !== index,
                }"
                :draggable="!col.required"
                @dragstart="onDragStart(index)"
                @dragover.prevent="onDragOver(index)"
                @drop="onDrop(index)"
                @dragend="onDragEnd"
            >
                <span
                    class="drag-h"
                    :title="
                        col.required
                            ? $t('component.columnConfig.requiredCol')
                            : $t('component.columnConfig.dragSort')
                    "
                >
                    <i v-if="col.required" class="i-svg:lock" />
                    <i v-else class="i-svg:grip-vertical" />
                </span>
                <label class="col-cfg-name">
                    <input
                        type="checkbox"
                        :checked="col.visible"
                        :disabled="col.required"
                        @change="toggleColumn(index)"
                    />
                    <span>{{ col.label }}</span>
                    <em v-if="col.required" class="tag-req">{{
                        $t('component.columnConfig.required')
                    }}</em>
                </label>
                <span class="col-cfg-width">
                    {{ col.width ? `${col.width}px` : $t('component.columnConfig.auto') }}
                </span>
                <div class="col-cfg-fix">
                    <button
                        class="fix-btn"
                        :class="{ on: col.fixed === 'left' }"
                        :title="$t('component.columnConfig.fixLeft')"
                        @click="setFixed(index, 'left')"
                    >
                        <i class="i-svg:arrow-left" />
                    </button>
                    <button
                        class="fix-btn"
                        :class="{ on: col.fixed === 'right' }"
                        :title="$t('component.columnConfig.fixRight')"
                        @click="setFixed(index, 'right')"
                    >
                        <i class="i-svg:arrow-right" />
                    </button>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="modal-foot">
                <el-button @click="handleReset">{{ $t('component.columnConfig.reset') }}</el-button>
                <div style="flex: 1" />
                <el-button @click="handleClose">{{ $t('component.columnConfig.cancel') }}</el-button>
                <el-button type="primary" @click="handleConfirm">{{
                    $t('component.columnConfig.apply')
                }}</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import type { ColumnConfigItem } from './types'

const props = defineProps<{
    modelValue: boolean
    columns: ColumnConfigItem[]
}>()

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    'update:columns': [columns: ColumnConfigItem[]]
}>()

const localColumns = ref<ColumnConfigItem[]>([])
const dragIndex = ref<number | null>(null)
const overIndex = ref<number | null>(null)

// Sync from props when dialog opens
watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            localColumns.value = props.columns.map((c) => ({ ...c }))
        }
    },
    { immediate: true }
)

const checkedCount = computed(() => localColumns.value.filter((c) => c.visible).length)
const allChecked = computed(() => checkedCount.value === localColumns.value.length)

function toggleAll() {
    const newVal = !allChecked.value
    localColumns.value = localColumns.value.map((c) => ({
        ...c,
        visible: newVal || !!c.required,
    }))
}

function toggleColumn(index: number) {
    localColumns.value[index].visible = !localColumns.value[index].visible
}

function setFixed(index: number, dir: 'left' | 'right') {
    const col = localColumns.value[index]
    col.fixed = col.fixed === dir ? false : dir
}

// Drag and drop
function onDragStart(index: number) {
    dragIndex.value = index
}
function onDragOver(index: number) {
    overIndex.value = index
}
function onDrop(index: number) {
    if (dragIndex.value === null || dragIndex.value === index) {
        dragIndex.value = null
        overIndex.value = null
        return
    }
    const next = [...localColumns.value]
    const [moved] = next.splice(dragIndex.value, 1)
    next.splice(index, 0, moved)
    localColumns.value = next
    dragIndex.value = null
    overIndex.value = null
}
function onDragEnd() {
    dragIndex.value = null
    overIndex.value = null
}

function handleReset() {
    localColumns.value = props.columns.map((c) => ({ ...c }))
}

function handleClose() {
    emit('update:modelValue', false)
}

function handleConfirm() {
    emit('update:columns', localColumns.value)
    emit('update:modelValue', false)
}
</script>

<style lang="scss" scoped>
/* Modal head */
.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.modal-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary);

    &::before {
        content: "";
        width: 3px;
        height: 14px;
        background: var(--color-brand);
        border-radius: 2px;
    }

    .sub {
        font-size: 12px;
        font-weight: 400;
        color: var(--color-text-disabled);
        margin-left: 4px;
    }
}

.modal-close {
    width: 26px;
    height: 26px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-disabled);
    background: transparent;
    border: none;
    cursor: pointer;

    &:hover {
        background: var(--color-divider);
        color: var(--color-text-secondary);
    }
}

/* Modal foot */
.modal-foot {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

/* Column config styles */
.col-cfg-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 4px 12px;
    border-bottom: 1px dashed var(--color-border);
    margin-bottom: 10px;
}

.col-cfg-all {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--color-text-secondary);
    cursor: pointer;
}

.col-cfg-stat {
    font-size: 12px;
    color: var(--color-text-tertiary);

    b {
        color: var(--color-brand-active);
        font-weight: 700;
    }
}

.col-cfg-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 380px;
    overflow-y: auto;
    padding: 2px;
}

.col-cfg-row {
    display: grid;
    grid-template-columns: 22px 1fr 70px 60px;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid var(--color-divider);
    border-radius: 4px;
    background: var(--color-surface);
    transition: all 0.12s;

    &:hover {
        border-color: var(--el-color-primary-light-8, var(--color-border));
        background: var(--color-surface-sunken);
    }

    &.dragging {
        opacity: 0.4;
    }

    &.over {
        border-color: var(--color-brand);
        background: var(--el-color-primary-light-9);
        box-shadow: inset 0 -2px 0 var(--color-brand);
    }

    .drag-h {
        color: var(--color-border-strong);
        cursor: grab;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    &[draggable="false"] .drag-h {
        color: var(--color-text-disabled);
        cursor: not-allowed;
    }

    &:hover .drag-h {
        color: var(--color-text-tertiary);
    }
}

.col-cfg-name {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--color-text-primary);
    cursor: pointer;
    user-select: none;

    input[type="checkbox"] {
        margin: 0;
    }

    .tag-req {
        font-style: normal;
        font-size: 10.5px;
        padding: 1px 6px;
        border-radius: 2px;
        background: var(--color-divider);
        color: var(--color-text-tertiary);
        margin-left: 2px;
    }
}

.col-cfg-width {
    font-size: 11.5px;
    color: var(--color-text-disabled);
    text-align: right;
}

.col-cfg-fix {
    display: inline-flex;
    gap: 4px;
    justify-content: flex-end;
}

.fix-btn {
    width: 22px;
    height: 22px;
    border-radius: 3px;
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-tertiary);
    transition: all 0.12s;
    cursor: pointer;

    &:hover {
        color: var(--color-brand);
        border-color: var(--color-brand);
    }

    &.on {
        background: var(--el-color-primary-light-9);
        color: var(--color-brand-active);
        border-color: var(--color-brand);
    }
}
</style>

<style lang="scss">
/* Unscoped overrides for el-dialog */
.col-cfg-dialog {
    .el-dialog__header {
        padding: 14px 20px;
        margin: 0;
        border-bottom: 1px solid var(--color-divider);
    }

    .el-dialog__body {
        padding: 20px 24px 4px;
    }

    .el-dialog__footer {
        padding: 14px 20px;
        border-top: 1px solid var(--color-divider);
        background: var(--color-surface-sunken);
    }
}
</style>
