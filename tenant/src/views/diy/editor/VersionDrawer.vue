<template>
  <el-drawer v-model="visible" :title="t('diyEditor.versions')" size="480px" @open="load">
    <el-table :data="versions" v-loading="loading" :empty-text="t('diyEditor.versionsEmpty')">
      <el-table-column prop="version_no" :label="t('diyEditor.publishVersionNo')" width="70" />
      <el-table-column :label="t('diyEditor.versionNote')" min-width="120" show-overflow-tooltip>
        <template #default="{ row }">
          {{ row.note?.trim() ? row.note : '—' }}
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('diyEditor.publishedAt')" width="160" />
      <el-table-column :label="t('diyEditor.versionActions')" width="90">
        <template #default="{ row }">
          <el-button
            size="small"
            :loading="restoring === row.id"
            :disabled="restoring !== null"
            @click="doRestore(row.id)"
          >
            {{ t('diyEditor.restore') }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </el-drawer>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { diyApi, type DiyVersion } from '@/api/diy'

const { t } = useI18n()
const visible = defineModel<boolean>({ required: true })
const props = defineProps<{ pageKey: string }>()
const emit = defineEmits<{ (e: 'restored'): void }>()
const versions = ref<DiyVersion[]>([])
const loading = ref(false)
const restoring = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    const res = await diyApi.listPageVersions(props.pageKey)
    versions.value = res.data || []
  } finally {
    loading.value = false
  }
}

async function doRestore(id: number) {
  if (restoring.value !== null) return
  restoring.value = id
  try {
    await diyApi.restorePageVersion(props.pageKey, id)
    ElMessage.success(t('diyEditor.restored'))
    visible.value = false
    emit('restored')
  } catch {
    // 错误已由响应拦截器提示
  } finally {
    restoring.value = null
  }
}
</script>
