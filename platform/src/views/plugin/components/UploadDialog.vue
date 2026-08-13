<script setup lang="ts">
import { UploadFilled } from '@element-plus/icons-vue'
import { ElMessage, type UploadFile } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { pluginApi } from '@/api/plugin'

const { t } = useI18n()

const visible = defineModel<boolean>('visible', { required: true })
const emit = defineEmits<{ uploaded: [pluginId: number] }>()

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
        const res = await pluginApi.upload(file.value)
        ElMessage.success(t('pluginMgr.uploadSuccess'))
        visible.value = false
        emit('uploaded', res.data.plugin_id)
    } finally {
        loading.value = false
        file.value = null
    }
}
</script>

<template>
    <el-dialog v-model="visible" class="dialog-md" :title="$t('pluginMgr.uploadTitle')">
        <el-upload
            drag
            :auto-upload="false"
            :show-file-list="false"
            accept=".zip"
            :on-change="handleFileChange"
        >
            <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
            <div class="el-upload__text">
                {{ $t('pluginMgr.dragPrefix') }}<em>{{ $t('pluginMgr.dragAction') }}</em>
            </div>
            <template #tip>
                <div class="el-upload__tip">
                    {{ $t('pluginMgr.uploadTip1') }}<br />
                    {{ $t('pluginMgr.uploadTip2') }}
                </div>
            </template>
        </el-upload>
        <p v-if="file" style="margin-top: 12px; color: #67c23a">
            {{
                $t('pluginMgr.selectedFile', {
                    name: file.name,
                    size: (file.size / 1024).toFixed(1)
                })
            }}
        </p>
        <template #footer>
            <el-button @click="visible = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="loading" @click="submit">
                {{ $t('pluginMgr.uploadAndVerify') }}
            </el-button>
        </template>
    </el-dialog>
</template>
