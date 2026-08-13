<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { ref, watch } from 'vue'

interface Field {
    key: string
    label: string
    type: string
    options?: string[]
    default?: any
    required?: boolean
    placeholder?: string
    tip?: string
    min?: number
    max?: number
    step?: number
}

const props = defineProps<{
    schema: { fields: Field[] } | null
    values: Record<string, any>
}>()
const emit = defineEmits<{
    (e: 'submit', v: Record<string, any>): void
}>()

const form = ref<Record<string, any>>({})

function reset() {
    if (!props.schema) return
    const next: Record<string, any> = {}
    for (const f of props.schema.fields) {
        const existing = props.values[f.key]
        if (existing !== undefined && existing !== null && existing !== '') {
            next[f.key] = f.type === 'number' ? Number(existing) : existing
        } else if (f.default !== undefined) {
            next[f.key] = f.default
        } else {
            // number 默认 0；switch 默认 false；其它默认空串
            next[f.key] = f.type === 'number' ? 0 : f.type === 'switch' ? false : ''
        }
    }
    form.value = next
}

watch(() => [props.schema, props.values], reset, { immediate: true })

function isEmpty(v: any): boolean {
    return v === '' || v === null || v === undefined
}

function submit() {
    if (!props.schema) return
    for (const f of props.schema.fields) {
        // number 字段：0 是合法值，不能用 !v 误判；switch 字段：false 也是合法值
        if (f.required && f.type !== 'switch' && isEmpty(form.value[f.key])) {
            ElMessage.error(`${f.label} 不能为空`)
            return
        }
    }
    emit('submit', { ...form.value })
}
</script>

<template>
    <el-form v-if="schema" label-width="180px" style="max-width: 640px">
        <el-form-item v-for="f in schema.fields" :key="f.key" :label="f.label">
            <el-input v-if="f.type === 'text'" v-model="form[f.key]" :placeholder="f.placeholder" />
            <el-input
                v-else-if="f.type === 'password'"
                v-model="form[f.key]"
                type="password"
                show-password
            />
            <el-input-number
                v-else-if="f.type === 'number'"
                v-model="form[f.key]"
                :min="f.min"
                :max="f.max"
                :step="f.step ?? 1"
                :placeholder="f.placeholder"
                controls-position="right"
                style="width: 220px"
            />
            <el-select v-else-if="f.type === 'select'" v-model="form[f.key]">
                <el-option v-for="o in f.options || []" :key="o" :label="o" :value="o" />
            </el-select>
            <el-switch v-else-if="f.type === 'switch'" v-model="form[f.key]" />
            <el-input v-else v-model="form[f.key]" :placeholder="f.placeholder" />
            <div v-if="f.tip" class="form-tip">{{ f.tip }}</div>
        </el-form-item>
        <el-form-item>
            <el-button type="primary" @click="submit">保存</el-button>
        </el-form-item>
    </el-form>
    <div v-else class="hint">未提供配置 Schema</div>
</template>

<style scoped>
.hint {
    color: var(--el-text-color-secondary);
    font-size: 13px;
    padding: 8px 0;
}
</style>
