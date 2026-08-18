<template>
    <div class="generator-page">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('generator.title') }}</div>
                <div class="page-desc">{{ $t('generator.desc') }}</div>
            </div>
        </div>

        <el-steps :active="step" finish-status="success" class="mb-6">
            <el-step :title="$t('generator.step1')" />
            <el-step :title="$t('generator.step2')" />
            <el-step :title="$t('generator.step3')" />
        </el-steps>

        <!-- Step 1: 选择数据表 -->
        <template v-if="step === 0">
            <el-card class="search-card" shadow="never">
                <el-form inline class="search-form">
                    <el-form-item>
                        <el-input
                            v-model="tableSearch"
                            :placeholder="$t('generator.searchTable')"
                            clearable
                            style="width: 300px"
                        >
                            <template #prefix><i class="i-svg:search" /></template>
                        </el-input>
                    </el-form-item>
                </el-form>
            </el-card>
            <ProTable
                :title="$t('generator.step1')"
                storage-key="generator-tables"
                row-key="name"
                :columns="tableColumns"
                :data="filteredTables"
                :loading="tableLoading"
                :pagination="tablePagination"
                :show-pagination="false"
                highlight-current-row
                @current-change="handleTableSelect"
            />
            <div class="mt-4 flex justify-end">
                <el-button type="primary" :disabled="!selectedTable" @click="goStep2">{{
                    $t('generator.nextStep')
                }}</el-button>
            </div>
        </template>

        <!-- Step 2: 配置 -->
        <div v-if="step === 1" class="set-card">
            <div class="set-card-head">
                <h3>{{ $t('generator.basicConfig') }} — {{ selectedTable }}</h3>
            </div>
            <div class="set-card-body">
            <el-form label-width="120px" class="mb-6">
                <el-row :gutter="24">
                    <el-col :span="8">
                        <el-form-item :label="$t('generator.moduleName')">
                            <el-input
                                v-model="config.module_name"
                                :placeholder="$t('generator.moduleNamePlaceholder')"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('generator.modelName')">
                            <el-input
                                v-model="config.model_name"
                                :placeholder="$t('generator.modelNamePlaceholder')"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('generator.cnName')">
                            <el-input
                                v-model="config.table_comment"
                                :placeholder="$t('generator.cnNamePlaceholder')"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>

            <h4 class="mb-3">{{ $t('generator.fieldConfig') }}</h4>
            <el-table :data="columns" border size="small" max-height="400">
                <el-table-column prop="name" :label="$t('generator.fieldName')" width="140" />
                <el-table-column prop="comment" :label="$t('generator.comment')" width="120" />
                <el-table-column prop="raw_type" :label="$t('generator.fieldType')" width="130" />
                <el-table-column :label="$t('generator.formType')" width="130">
                    <template #default="{ row }">
                        <el-select v-model="row.form_type" size="small" :disabled="!row.in_form">
                            <el-option
                                :label="$t('generator.formTypeOptions.input')"
                                value="input"
                            />
                            <el-option
                                :label="$t('generator.formTypeOptions.textarea')"
                                value="textarea"
                            />
                            <el-option
                                :label="$t('generator.formTypeOptions.number')"
                                value="number"
                            />
                            <el-option
                                :label="$t('generator.formTypeOptions.switch')"
                                value="switch"
                            />
                            <el-option
                                :label="$t('generator.formTypeOptions.select')"
                                value="select"
                            />
                            <el-option
                                :label="$t('generator.formTypeOptions.date')"
                                value="datepicker"
                            />
                            <el-option
                                :label="$t('generator.formTypeOptions.image')"
                                value="image"
                            />
                        </el-select>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('generator.listShow')" width="80" align="center">
                    <template #default="{ row }">
                        <el-checkbox v-model="row.in_list" />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('generator.formShow')" width="80" align="center">
                    <template #default="{ row }">
                        <el-checkbox v-model="row.in_form" />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('generator.searchable')" width="80" align="center">
                    <template #default="{ row }">
                        <el-checkbox v-model="row.searchable" />
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-between">
                <el-button @click="step = 0">{{ $t('generator.prevStep') }}</el-button>
                <el-button type="primary" :loading="previewLoading" @click="handlePreview">{{
                    $t('generator.previewCode')
                }}</el-button>
            </div>
            </div>
        </div>

        <!-- Step 3: 预览 & 生成 -->
        <div v-if="step === 2" class="set-card">
            <div class="set-card-head">
                <h3>{{ $t('generator.previewTitle') }}</h3>
            </div>
            <div class="set-card-body">
            <el-tabs v-model="activeTab">
                <el-tab-pane
                    v-for="(file, key) in previewFiles"
                    :key="key"
                    :label="key"
                    :name="key"
                >
                    <div class="text-sm text-gray-500 mb-2">{{ file.path }}</div>
                    <div class="code-block">
                        <pre><code>{{ file.content }}</code></pre>
                    </div>
                </el-tab-pane>
            </el-tabs>
            <div class="mt-4 flex justify-between">
                <el-button @click="step = 1">{{ $t('generator.prevStep') }}</el-button>
                <el-button type="primary" :loading="generateLoading" @click="handleGenerate">{{
                    $t('generator.confirmGenerate')
                }}</el-button>
            </div>
            </div>
        </div>

        <!-- 生成结果 -->
        <el-dialog v-model="showResult" class="dialog-md" :title="$t('generator.resultTitle')">
            <el-table :data="generateResult" size="small" border>
                <el-table-column prop="path" :label="$t('generator.filePath')" min-width="300" />
                <el-table-column
                    prop="status"
                    :label="$t('generator.statusLabel')"
                    width="100"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-tag
                            :type="row.status === 'created' ? 'success' : 'warning'"
                            size="small"
                            >{{
                                row.status === 'created'
                                    ? $t('generator.statusCreated')
                                    : $t('generator.statusSkipped')
                            }}</el-tag
                        >
                    </template>
                </el-table-column>
                <el-table-column prop="reason" :label="$t('generator.reason')" width="120" />
            </el-table>
            <div v-if="routeSnippet" class="mt-4">
                <h4 class="mb-2">{{ $t('generator.routeHint') }}</h4>
                <div class="code-block">
                    <pre><code>{{ routeSnippet }}</code></pre>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { generatorApi } from '@/api/generator'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'

const { t } = useI18n()

const step = ref(0)
const tableLoading = ref(false)
const tables = ref<any[]>([])
const tableSearch = ref('')
const selectedTable = ref('')
const columns = ref<any[]>([])
const previewLoading = ref(false)
const generateLoading = ref(false)
const previewFiles = ref<Record<string, any>>({})
const activeTab = ref('controller')
const showResult = ref(false)
const generateResult = ref<any[]>([])
const routeSnippet = ref('')

const config = reactive({
    table_name: '',
    module_name: '',
    model_name: '',
    table_comment: ''
})

const filteredTables = computed(() => {
    if (!tableSearch.value) return tables.value
    const kw = tableSearch.value.toLowerCase()
    return tables.value.filter(
        (item: any) =>
            item.name.toLowerCase().includes(kw) || (item.comment || '').toLowerCase().includes(kw)
    )
})

const tableColumns = computed<ProColumn[]>(() => [
    { key: 'name', label: t('generator.tableName'), minWidth: 200, required: true },
    { key: 'comment', label: t('generator.tableComment'), minWidth: 200 },
    { key: 'engine', label: t('generator.engine'), width: 100 },
    { key: 'rows', label: t('generator.rows'), width: 80, align: 'center' }
])

const tablePagination = computed(() => ({
    page: 1,
    limit: filteredTables.value.length || 20,
    total: filteredTables.value.length
}))

const fetchTables = async () => {
    tableLoading.value = true
    try {
        const res = await generatorApi.getTables()
        tables.value = res.data || []
    } finally {
        tableLoading.value = false
    }
}

const handleTableSelect = (row: any) => {
    if (row) {
        selectedTable.value = row.name
        config.table_comment = row.comment || ''
    }
}

const goStep2 = async () => {
    config.table_name = selectedTable.value
    // 自动推断模块名和模型名
    const parts = selectedTable.value.replace(/^[a-z]+_/, '').split('_')
    config.model_name =
        config.model_name ||
        parts.map((p: string) => p.charAt(0).toUpperCase() + p.slice(1)).join('')
    config.module_name = config.module_name || 'business'
    config.table_comment = config.table_comment || config.model_name

    try {
        const res = await generatorApi.getColumns(selectedTable.value)
        columns.value = res.data || []
        step.value = 1
    } catch (e) {
        ElMessage.error(t('generator.getColumnsFailed'))
    }
}

const handlePreview = async () => {
    if (!config.module_name || !config.model_name) {
        ElMessage.warning(t('generator.fillModuleAndModel'))
        return
    }
    previewLoading.value = true
    try {
        const res = await generatorApi.preview({ ...config, columns: columns.value })
        previewFiles.value = res.data || {}
        activeTab.value = Object.keys(previewFiles.value)[0] || 'controller'
        step.value = 2
    } finally {
        previewLoading.value = false
    }
}

const handleGenerate = async () => {
    generateLoading.value = true
    try {
        const res = await generatorApi.generate({ ...config, columns: columns.value })
        generateResult.value = res.data.files || []
        routeSnippet.value = res.data.route || ''
        showResult.value = true
        ElMessage.success(t('generator.generateSuccess'))
    } finally {
        generateLoading.value = false
    }
}

onMounted(fetchTables)
</script>

<style lang="scss" scoped>
.generator-page {
    width: 100%;
}

.code-block {
    background: var(--gray-100);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 16px;
    max-height: 500px;
    overflow: auto;

    pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 13px;
        line-height: 1.6;
    }
}
</style>
