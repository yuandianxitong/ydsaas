<script setup lang="ts">
import { UploadFilled } from '@element-plus/icons-vue'
import { ElMessage, type UploadFile } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { pluginApi } from '@/api/plugin'

const { t } = useI18n()

const props = defineProps<{ pluginId: number; pluginCode: string; currentVersion: string }>()
const visible = defineModel<boolean>('visible', { required: true })
const emit = defineEmits<{ upgraded: [] }>()

const file = ref<File | null>(null)
const loading = ref(false)

function handleFileChange(rawFile: UploadFile) {
    file.value = rawFile.raw ?? null
}

async function submit() {
    if (!file.value) {
        ElMessage.error(t('pluginMgr.selectZipFirst'))
        return
    }
    loading.value = true
    try {
        const res = await pluginApi.upgrade(props.pluginId, file.value)
        ElMessage.success(t('pluginMgr.upgradeSuccess', { from: res.data.from, to: res.data.to }))
        visible.value = false
        emit('upgraded')
    } finally {
        loading.value = false
        file.value = null
    }
}
</script>

<template>
    <el-dialog
        v-model="visible"
        class="dialog-md"
        :title="$t('pluginMgr.upgradeTitle', { code: pluginCode, version: currentVersion })"
    >
        <el-upload
            drag
            :auto-upload="false"
            :show-file-list="false"
            accept=".zip"
            :on-change="handleFileChange"
        >
            <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
            <div class="el-upload__text">
                {{ $t('pluginMgr.upgradeDragPrefix') }}<em>{{ $t('pluginMgr.dragAction') }}</em>
            </div>
            <template #tip>
                <div class="el-upload__tip">
                    {{ $t('pluginMgr.upgradeTip', { version: currentVersion }) }}
                </div>
            </template>
        </el-upload>
        <p v-if="file" style="margin-top: 12px; color: #67c23a">
            {{ $t('pluginMgr.upgradeSelected', { name: file.name }) }}
        </p>
        <template #footer>
            <el-button @click="visible = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="loading" @click="submit">
                {{ $t('pluginMgr.uploadAndUpgrade') }}
            </el-button>
        </template>
    </el-dialog>
</template>
