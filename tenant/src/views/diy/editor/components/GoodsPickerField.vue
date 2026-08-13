<template>
    <div class="gpf">
        <div class="gpf__summary">
            <span>已选 {{ selectedIds.length }} 件商品</span>
            <el-button type="primary" link @click="open">选择商品</el-button>
        </div>
        <div v-if="selectedLabels.length" class="gpf__tags">
            <el-tag
                v-for="it in selectedLabels"
                :key="it.value"
                closable
                size="small"
                @close="remove(it.value)"
            >
                {{ it.label }}
            </el-tag>
        </div>
        <GoodsPickerDialog v-model="visible" :selected-ids="selectedIds" @confirm="onConfirm" />
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { myRequest } from '@/utils/request'

import GoodsPickerDialog from './GoodsPickerDialog.vue'

const props = defineProps<{ modelValue: number[] | any }>()
const emit = defineEmits<{
    (e: 'update:modelValue', v: number[]): void
    (e: 'begin'): void
}>()

const visible = ref(false)
const labelMap = ref<Record<number, string>>({})

const selectedIds = computed(() =>
    Array.isArray(props.modelValue)
        ? props.modelValue.map((x) => Number(x)).filter((n) => n > 0)
        : []
)

const selectedLabels = computed(() =>
    selectedIds.value.map((id) => ({
        value: id,
        label: labelMap.value[id] || `#${id}`,
    }))
)

async function loadLabels(ids: number[]) {
    if (!ids.length) return
    try {
        const opts = await myRequest.get<Array<{ label: string; value: number }>>(
            '/tenantapi/shop/goods/select'
        )
        const list = Array.isArray(opts.data) ? opts.data : []
        const map = { ...labelMap.value }
        for (const o of list) {
            const id = Number(o.value)
            if (ids.includes(id)) map[id] = String(o.label || `#${id}`)
        }
        labelMap.value = map
    } catch {
        /* ignore */
    }
}

function open() {
    emit('begin')
    visible.value = true
}

function onConfirm(ids: number[]) {
    emit('begin')
    emit('update:modelValue', ids)
    loadLabels(ids)
}

function remove(id: number) {
    emit('begin')
    emit(
        'update:modelValue',
        selectedIds.value.filter((x) => x !== id)
    )
}

watch(selectedIds, (ids) => loadLabels(ids), { immediate: true })
</script>

<style scoped>
.gpf {
    width: 100%;
}
.gpf__summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    color: var(--color-text-secondary, #909399);
}
.gpf__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
}
</style>
