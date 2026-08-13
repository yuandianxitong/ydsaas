<template>
    <div class="cfg-checkboxes">
        <el-checkbox-group :model-value="modelValue" @change="onChange">
            <el-checkbox v-for="o in options" :key="String(o.value)" :value="String(o.value)">
                {{ o.label }}
            </el-checkbox>
        </el-checkbox-group>
    </div>
</template>

<script setup lang="ts">
const props = defineProps<{
    modelValue: string[]
    options: Array<{ label: string; value: any }>
}>()
const emit = defineEmits<{
    (e: 'update:modelValue', v: string[]): void
    (e: 'begin'): void
}>()

function onChange(v: Array<string | number | boolean>) {
    emit('begin')
    emit('update:modelValue', v.map(String))
}
</script>

<style scoped>
.cfg-checkboxes :deep(.el-checkbox-group) {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px 8px;
}
.cfg-checkboxes :deep(.el-checkbox) {
    margin-right: 0;
    height: auto;
}
</style>
