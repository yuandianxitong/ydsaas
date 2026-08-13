<template>
    <el-cascader
        v-model="selectedValue"
        :options="regionOptions"
        :props="cascaderProps"
        :placeholder="placeholder"
        :disabled="disabled"
        clearable
        filterable
        @change="handleChange"
    />
</template>

<script lang="ts" setup>
import { computed, onMounted, ref, watch } from 'vue'

import { regionApi, type RegionTreeNode } from '@/api/region'

const props = withDefaults(
    defineProps<{
        modelValue?: (string | number)[]
        placeholder?: string
        level?: 2 | 3
        disabled?: boolean
    }>(),
    {
        modelValue: () => [],
        placeholder: '请选择地区',
        level: 3,
        disabled: false
    }
)

const emit = defineEmits<{
    (e: 'update:modelValue', value: (string | number)[]): void
    /** value：所选各级 value 数组；labels：对应各级 label 数组（末级 label = labels[labels.length - 1]） */
    (e: 'change', value: (string | number)[], labels: string[]): void
}>()

const regionOptions = ref<RegionTreeNode[]>([])
const loading = ref(false)

const cascaderProps = computed(() => ({
    value: 'value',
    label: 'label',
    children: 'children',
    checkStrictly: false
}))

const selectedValue = computed({
    get: () => props.modelValue || [],
    set: (value: (string | number)[]) => {
        emit('update:modelValue', value || [])
    }
})

/**
 * Trim region tree to the specified level depth
 */
function trimToLevel(items: RegionTreeNode[], maxLevel: number, currentLevel = 1): RegionTreeNode[] {
    return items.map((item) => {
        const node: RegionTreeNode = { value: item.value, label: item.label }
        if (currentLevel < maxLevel && item.children?.length) {
            node.children = trimToLevel(item.children, maxLevel, currentLevel + 1)
        }
        return node
    })
}

/** 按所选 value 路径在树中逐级反查各级 label */
function resolveLabels(values: (string | number)[]): string[] {
    const labels: string[] = []
    let nodes = regionOptions.value
    for (const value of values) {
        const node = nodes.find((item) => item.value === value)
        if (!node) break
        labels.push(node.label)
        nodes = node.children || []
    }
    return labels
}

async function loadRegions() {
    loading.value = true
    try {
        const res = await regionApi.tree()
        const data = res.data || []
        regionOptions.value = props.level < 3 ? trimToLevel(data, props.level) : data
    } catch {
        // API unavailable — fall back to empty options silently
        regionOptions.value = []
    } finally {
        loading.value = false
    }
}

function handleChange(value: (string | number)[]) {
    const values = value || []
    emit('change', values, resolveLabels(values))
}

// Reload when level prop changes
watch(
    () => props.level,
    () => {
        loadRegions()
    }
)

onMounted(() => {
    loadRegions()
})
</script>
