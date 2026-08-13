<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { ref, watch } from 'vue'

import { pluginApi } from '@/api/plugin'

const props = defineProps<{ pluginCode: string; pluginName: string }>()
const visible = defineModel<boolean>('visible', { required: true })

const configEntries = ref<Array<{ key: string; value: string }>>([])
const loading = ref(false)
const saving = ref(false)

async function load() {
    if (!props.pluginCode) return
    loading.value = true
    try {
        const res = await pluginApi.getConfig(props.pluginCode)
        configEntries.value = Object.entries(res.data || {}).map(([key, value]) => ({
            key,
            value: value ?? ''
        }))
        if (configEntries.value.length === 0) {
            configEntries.value = [{ key: '', value: '' }]
        }
    } finally {
        loading.value = false
    }
}

function addEntry() {
    configEntries.value.push({ key: '', value: '' })
}
function removeEntry(i: number) {
    configEntries.value.splice(i, 1)
}

async function save() {
    saving.value = true
    try {
        const config: Record<string, string | null> = {}
        for (const e of configEntries.value) {
            const k = (e.key ?? '').trim()
            if (k !== '') config[k] = e.value ?? ''
        }
        await pluginApi.updateConfig(props.pluginCode, config)
        ElMessage.success('配置已保存')
        visible.value = false
    } finally {
        saving.value = false
    }
}

watch(visible, (v) => v && load())
</script>

<template>
    <el-dialog v-model="visible" :title="`配置 — ${pluginName}`" class="dlg-md">
        <div v-loading="loading">
            <div
                v-for="(e, i) in configEntries"
                :key="i"
                style="display: flex; gap: 8px; margin-bottom: 8px"
            >
                <el-input v-model="e.key" placeholder="键" style="flex: 1" />
                <el-input v-model="e.value" placeholder="值" style="flex: 2" />
                <el-button text type="danger" @click="removeEntry(i)">删除</el-button>
            </div>
            <el-button @click="addEntry">添加配置项</el-button>
        </div>
        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="saving" @click="save">保存</el-button>
        </template>
    </el-dialog>
</template>
