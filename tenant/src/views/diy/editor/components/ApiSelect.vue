<!-- tenant/src/views/diy/editor/components/ApiSelect.vue -->
<template>
    <el-select
        :model-value="displayValue"
        :multiple="multiple"
        clearable
        filterable
        :placeholder="placeholder || '请选择'"
        @change="onChange"
    >
        <el-option v-for="o in options" :key="String(o.value)" :label="o.label" :value="o.value" />
    </el-select>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { useApiOptions } from '../useApiOptions'

const props = withDefaults(
    defineProps<{
        modelValue: any
        url: string
        multiple?: boolean
        placeholder?: string
        /** 单选清空时写回值；未选展示占位（0/''/null 视为空） */
        emptyValue?: 0 | '' | null
    }>(),
    { multiple: false, placeholder: '请选择', emptyValue: 0 }
)

const emit = defineEmits<{ (e: 'update:modelValue', v: any): void }>()
const options = useApiOptions(props.url)

function isEmptySingle(v: any): boolean {
    return v === undefined || v === null || v === '' || v === 0 || v === '0'
}

const displayValue = computed(() => {
    if (props.multiple) {
        return Array.isArray(props.modelValue) ? props.modelValue : []
    }
    return isEmptySingle(props.modelValue) ? undefined : props.modelValue
})

function onChange(v: any) {
    if (props.multiple) {
        emit('update:modelValue', Array.isArray(v) ? v : [])
        return
    }
    if (v === undefined || v === null || v === '') {
        emit('update:modelValue', props.emptyValue)
        return
    }
    emit('update:modelValue', v)
}
</script>
