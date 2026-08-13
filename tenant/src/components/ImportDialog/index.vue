<template>
    <el-dialog
        :model-value="modelValue"
        title=""
        :show-close="false"
        :close-on-click-modal="false"
        append-to-body
        class="import-dialog dlg-lg"
        @update:model-value="emit('update:modelValue', $event)"
        @close="handleClose"
    >
        <template #header>
            <div class="modal-head">
                <div class="modal-title">
                    {{ title || $t('component.importDialog.defaultTitle') }}
                    <span class="sub">{{ $t('component.importDialog.subtitle') }}</span>
                </div>
                <button class="modal-close" @click="handleClose">
                    <i class="i-svg:x" />
                </button>
            </div>
        </template>

        <!-- Step indicator -->
        <div class="imp-steps">
            <template v-for="(s, i) in steps" :key="s.n">
                <div
                    class="imp-step"
                    :class="{ on: step === s.n, done: step > s.n }"
                >
                    <div class="imp-step-n">
                        <i v-if="step > s.n" class="i-svg:check" />
                        <template v-else>{{ s.n }}</template>
                    </div>
                    <div class="imp-step-txt">
                        <div class="t">{{ s.t }}</div>
                        <div class="d">{{ s.d }}</div>
                    </div>
                </div>
                <div
                    v-if="i < steps.length - 1"
                    class="imp-step-line"
                    :class="{ done: step > s.n }"
                />
            </template>
        </div>

        <!-- Step 1: Download template + Upload file -->
        <div v-if="step === 1" class="imp-body">
            <div class="imp-tpl">
                <div class="imp-tpl-ic">
                    <i class="i-svg:file-text" />
                </div>
                <div class="imp-tpl-txt">
                    <div class="t">{{ $t('component.importDialog.step1Title') }}</div>
                    <div class="d">{{ $t('component.importDialog.step1Desc') }}</div>
                </div>
                <div class="imp-tpl-btns">
                    <el-button v-if="templateUrl" size="small" type="primary" @click="downloadTemplate">
                        <i class="i-svg:download" />
                        {{ $t('component.importDialog.downloadTemplate') }}
                    </el-button>
                    <el-button v-else size="small" type="primary" disabled>
                        <i class="i-svg:download" />
                        {{ $t('component.importDialog.templateUnavailable') }}
                    </el-button>
                </div>
            </div>

            <div class="imp-sec-label">{{ $t('component.importDialog.step2Title') }}</div>
            <label
                class="imp-drop"
                :class="{ over: dragOver, 'has-file': !!fileMeta }"
                @dragover.prevent="dragOver = true"
                @dragleave="dragOver = false"
                @drop.prevent="onDrop"
            >
                <input
                    ref="fileInputRef"
                    type="file"
                    hidden
                    accept=".xlsx,.xls,.csv"
                    @change="onFileChange"
                />
                <template v-if="!fileMeta">
                    <div class="imp-drop-ic">
                        <i class="i-svg:upload" />
                    </div>
                    <div class="imp-drop-t">
                        {{ $t('component.importDialog.dropText') }}
                        <span class="lk">{{ $t('component.importDialog.dropLink') }}</span>
                    </div>
                    <div class="imp-drop-d">
                        {{ $t('component.importDialog.dropDesc') }}
                    </div>
                </template>
                <template v-else>
                    <div class="imp-file">
                        <div class="imp-file-ic">
                            <i class="i-svg:file-text" />
                        </div>
                        <div class="imp-file-txt">
                            <div class="nm">{{ fileMeta.name }}</div>
                            <div class="mt">
                                {{ fileMeta.size }} · {{ $t('component.importDialog.fileSelected') }}
                            </div>
                        </div>
                        <button class="imp-file-x" @click.prevent="clearFile">
                            <i class="i-svg:x" />
                        </button>
                    </div>
                </template>
            </label>

            <div v-if="notes.length" class="imp-note">
                <div class="t">{{ $t('component.importDialog.notesTitle') }}</div>
                <ul>
                    <li v-for="(n, i) in notes" :key="i">{{ n }}</li>
                </ul>
            </div>
        </div>

        <!-- Step 2: Validation preview -->
        <div v-if="step === 2" class="imp-body">
            <div class="imp-sum">
                <div class="imp-sum-cell">
                    <div class="l">{{ $t('component.importDialog.sumFile') }}</div>
                    <div class="v">{{ fileMeta?.name || '-' }}</div>
                </div>
                <div class="imp-sum-cell">
                    <div class="l">{{ $t('component.importDialog.sumTotal') }}</div>
                    <div class="v num">{{ previewList.length }}</div>
                </div>
                <div class="imp-sum-cell ok">
                    <div class="l">{{ $t('component.importDialog.sumOk') }}</div>
                    <div class="v num">{{ okCount }}</div>
                </div>
                <div class="imp-sum-cell err">
                    <div class="l">{{ $t('component.importDialog.sumErr') }}</div>
                    <div class="v num">{{ errCount }}</div>
                </div>
            </div>

            <div class="imp-tbl-wrap">
                <div class="imp-tbl-head">
                    <div class="l">{{ $t('component.importDialog.previewTitle') }}</div>
                    <div class="r">
                        <span class="legend-ok"><i />{{ $t('component.importDialog.legendOk') }}</span>
                        <span class="legend-err"><i />{{ $t('component.importDialog.legendErr') }}</span>
                    </div>
                </div>
                <div class="imp-tbl-scroll">
                    <table class="imp-tbl">
                        <thead>
                            <tr>
                                <th style="width: 48px">{{ $t('component.importDialog.colRowNum') }}</th>
                                <th v-for="col in previewColumns" :key="col.key">{{ col.label }}</th>
                                <th style="width: 60px">{{ $t('component.importDialog.colStatus') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in previewList"
                                :key="p.row"
                                :class="{ 'has-err': !p.ok }"
                            >
                                <td class="rn">{{ p.row }}</td>
                                <td
                                    v-for="col in previewColumns"
                                    :key="col.key"
                                    :class="{
                                        'err-cell': !p.ok && (p.err_fields?.includes(col.key) ?? false),
                                    }"
                                >
                                    {{ p[col.key] ?? '' }}
                                </td>
                                <td>
                                    <span
                                        v-if="p.ok"
                                        class="dot-ok"
                                        :title="$t('component.importDialog.sumOk')"
                                    />
                                    <span
                                        v-else
                                        class="dot-err"
                                        :title="p.err"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="errCount > 0" class="imp-err-bar">
                    <i class="i-svg:triangle-alert" />
                    {{ $t('component.importDialog.errBarPrefix') }} <b>{{ errCount }}</b>
                    {{ $t('component.importDialog.errBarMid') }}<b>{{ $t('component.importDialog.errBarSuffix') }}</b>{{ $t('component.importDialog.errBarEnd') }}
                </div>
            </div>
        </div>

        <!-- Step 3: Import complete -->
        <div v-if="step === 3" class="imp-body imp-done">
            <div class="imp-done-ic">
                <i class="i-svg:check" />
            </div>
            <div class="imp-done-t">{{ $t('component.importDialog.doneTitle') }}</div>
            <div class="imp-done-d">
                {{ $t('component.importDialog.doneDesc', { total: doneStats.total }) }}
            </div>

            <div class="imp-done-stats">
                <div class="cell ok">
                    <div class="n">{{ doneStats.imported }}</div>
                    <div class="l">{{ $t('component.importDialog.statImported') }}</div>
                </div>
                <div class="cell err">
                    <div class="n">{{ doneStats.skipped }}</div>
                    <div class="l">{{ $t('component.importDialog.statSkipped') }}</div>
                </div>
            </div>

            <div v-if="doneStats.errors?.length" class="imp-done-errors">
                <div class="t">{{ $t('component.importDialog.errorsTitle') }}</div>
                <ul>
                    <li v-for="(err, i) in doneStats.errors || []" :key="i">
                        {{ $t('component.importDialog.errorRow', { row: err.row, msg: err.msg }) }}
                    </li>
                </ul>
            </div>

            <div class="imp-done-acts">
                <el-button @click="resetToStep1">
                    {{ $t('component.importDialog.continueImport') }}
                </el-button>
            </div>
        </div>

        <template #footer>
            <!-- Step 1 footer -->
            <div v-if="step === 1" class="modal-foot">
                <el-button @click="handleClose">{{ $t('component.importDialog.cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="loading"
                    :disabled="!previewFn || !uploadFile"
                    @click="goStep2"
                >
                    {{
                        uploadFile
                            ? $t('component.importDialog.nextStep')
                            : $t('component.importDialog.selectFileFirst')
                    }}
                </el-button>
            </div>
            <!-- Step 2 footer -->
            <div v-else-if="step === 2" class="modal-foot">
                <el-button @click="step = 1">{{ $t('component.importDialog.prevStep') }}</el-button>
                <div style="flex: 1" />
                <el-button @click="handleClose">{{ $t('component.importDialog.cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="loading"
                    :disabled="okCount === 0 || !confirmFn"
                    @click="goStep3"
                >
                    {{ $t('component.importDialog.confirmImport', { count: okCount }) }}
                </el-button>
            </div>
            <!-- Step 3 footer -->
            <div v-else class="modal-foot">
                <el-button @click="handleClose">{{ $t('component.importDialog.close') }}</el-button>
                <el-button type="primary" @click="handleDone">
                    {{ $t('component.importDialog.backToList') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { getToken } from '@/utils/auth'

export interface PreviewColumn {
    key: string
    label: string
}

export interface PreviewRow {
    row: number
    ok: boolean
    err?: string
    err_fields?: string[]
    [key: string]: any
}

export interface PreviewResult {
    list: PreviewRow[]
    summary?: { total: number; ok: number; err: number }
    file_path?: string
}

export interface ConfirmResult {
    imported: number
    skipped: number
    errors?: { row: number; msg: string }[]
    total?: number
}

const { t } = useI18n()

const props = withDefaults(
    defineProps<{
        modelValue: boolean
        title?: string
        /** 模板下载 URL；若为相对路径会自动追加 token 查询参数。 */
        templateUrl?: string
        /** 上传文件 → 校验函数。未提供时"下一步"按钮禁用。 */
        previewFn?: (file: File) => Promise<{ data: PreviewResult }>
        /** 确认导入函数。filePath 为 previewFn 返回的服务端临时路径。 */
        confirmFn?: (filePath: string) => Promise<{ data: ConfirmResult }>
        /** 预览表格列。未提供时表格只渲染行号与状态列。 */
        previewColumns?: PreviewColumn[]
        /** 注意事项条目（纯文本，不渲染 HTML）。为空数组时不渲染注意事项区块。 */
        notes?: string[]
    }>(),
    {
        title: '',
        templateUrl: '',
        previewFn: undefined,
        confirmFn: undefined,
        previewColumns: () => [],
        notes: () => [],
    }
)

const emit = defineEmits<{
    'update:modelValue': [value: boolean]
    success: []
}>()

const step = ref(1)
const loading = ref(false)
const uploadFile = ref<File | null>(null)
const fileMeta = ref<{ name: string; size: string } | null>(null)
const dragOver = ref(false)
const fileInputRef = ref<HTMLInputElement>()

// Server side temp path returned from previewFn (for confirmFn)
const serverFilePath = ref<string>('')

// Preview state
const previewList = ref<PreviewRow[]>([])
const previewSummary = ref<{ total: number; ok: number; err: number }>({ total: 0, ok: 0, err: 0 })

// Done stats
const doneStats = ref<ConfirmResult & { total: number }>({ total: 0, imported: 0, skipped: 0, errors: [] })

const steps = computed(() => [
    {
        n: 1,
        t: t('component.importDialog.stepUploadTitle'),
        d: t('component.importDialog.stepUploadDesc'),
    },
    {
        n: 2,
        t: t('component.importDialog.stepPreviewTitle'),
        d: t('component.importDialog.stepPreviewDesc'),
    },
    {
        n: 3,
        t: t('component.importDialog.doneTitle'),
        d: t('component.importDialog.stepDoneDesc'),
    },
])

const okCount = computed(() => previewList.value.filter((p) => p.ok).length)
const errCount = computed(() => previewList.value.length - okCount.value)

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            resetToStep1()
        }
    }
)

function resetToStep1() {
    step.value = 1
    uploadFile.value = null
    fileMeta.value = null
    dragOver.value = false
    serverFilePath.value = ''
    previewList.value = []
    previewSummary.value = { total: 0, ok: 0, err: 0 }
    doneStats.value = { total: 0, imported: 0, skipped: 0, errors: [] }
    if (fileInputRef.value) fileInputRef.value.value = ''
}

function clearFile() {
    uploadFile.value = null
    fileMeta.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
}

function attachFile(f: File) {
    uploadFile.value = f
    fileMeta.value = {
        name: f.name,
        size: (f.size / 1024).toFixed(1) + ' KB',
    }
}

function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement
    const f = input.files?.[0]
    if (f) attachFile(f)
}

function onDrop(e: DragEvent) {
    dragOver.value = false
    const f = e.dataTransfer?.files?.[0]
    if (f) attachFile(f)
}

function downloadTemplate() {
    if (!props.templateUrl) return
    let url = props.templateUrl
    // 仅同源 URL 追加 token（用 URL 解析防 // 与 /\ 等绕过），供后端 admin_auth 下载鉴权
    try {
        const resolved = new URL(url, window.location.origin)
        if (resolved.origin === window.location.origin) {
            const token = getToken()
            if (token) {
                resolved.searchParams.set('token', token)
            }
            url = resolved.toString()
        }
        // 非同源 URL：保持 props.templateUrl 原样打开，不附带 token（预期行为，避免 token 泄露给第三方域）
    } catch {
        return
    }
    window.open(url, '_blank', 'noopener')
}

async function goStep2() {
    if (!props.previewFn || !uploadFile.value) return
    loading.value = true
    try {
        const res = await props.previewFn(uploadFile.value)
        const payload = (res as any)?.data ?? res
        previewList.value = payload?.list ?? []
        previewSummary.value = payload?.summary ?? {
            total: previewList.value.length,
            ok: previewList.value.filter((r: PreviewRow) => r.ok).length,
            err: previewList.value.filter((r: PreviewRow) => !r.ok).length,
        }
        serverFilePath.value = payload?.file_path ?? ''
        step.value = 2
    } catch (e) {
        console.error('预览失败:', e)
    } finally {
        loading.value = false
    }
}

async function goStep3() {
    if (!props.confirmFn) return
    loading.value = true
    try {
        const res = await props.confirmFn(serverFilePath.value)
        const payload = (res as any)?.data ?? res
        doneStats.value = {
            total: payload?.total ?? previewList.value.length,
            imported: payload?.imported ?? 0,
            skipped: payload?.skipped ?? 0,
            errors: payload?.errors ?? [],
        }
        step.value = 3
    } catch (e) {
        console.error('导入失败:', e)
    } finally {
        loading.value = false
    }
}

function handleClose() {
    emit('update:modelValue', false)
}

function handleDone() {
    emit('success')
    emit('update:modelValue', false)
}
</script>

<style lang="scss" scoped>
/* Modal head */
.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.modal-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    color: var(--gray-900);

    &::before {
        content: "";
        width: 3px;
        height: 14px;
        background: var(--color-brand);
        border-radius: 2px;
    }

    .sub {
        font-size: 12px;
        font-weight: 400;
        color: var(--color-text-disabled);
        margin-left: 4px;
    }
}

.modal-close {
    width: 26px;
    height: 26px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-disabled);
    background: transparent;
    border: none;
    cursor: pointer;

    &:hover {
        background: var(--color-divider);
        color: var(--color-text-secondary);
    }
}

/* Modal foot */
.modal-foot {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

/* Steps */
.imp-steps {
    display: flex;
    align-items: center;
    padding: 6px 8px 18px;
    border-bottom: 1px solid var(--color-divider);
    margin-bottom: 18px;
}

.imp-step {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.imp-step-n {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    background: var(--color-divider);
    color: var(--color-text-disabled);
    transition: all 0.2s;
}

.imp-step.on .imp-step-n {
    background: var(--color-brand);
    color: #fff;
    box-shadow: 0 0 0 4px var(--el-color-primary-light-9);
}

.imp-step.done .imp-step-n {
    background: var(--el-color-primary-light-9);
    color: var(--color-brand);
}

.imp-step-txt {
    .t {
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text-tertiary);
        line-height: 1.3;
    }

    .d {
        font-size: 11px;
        color: var(--color-text-disabled);
        margin-top: 2px;
    }
}

.imp-step.on .imp-step-txt .t {
    color: var(--gray-900);
}

.imp-step.done .imp-step-txt .t {
    color: var(--color-text-secondary);
}

.imp-step-line {
    flex: 1;
    height: 2px;
    margin: 0 14px;
    background: var(--color-divider);
    border-radius: 1px;
    position: relative;

    &.done {
        background: var(--el-color-primary-light-7, var(--el-color-primary-light-8));

        &::after {
            content: "";
            position: absolute;
            inset: 0;
            background: var(--color-brand);
            border-radius: 1px;
        }
    }
}

.imp-body {
    padding: 0 2px 8px;
}

/* Template download card */
.imp-tpl {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: linear-gradient(
        135deg,
        var(--el-color-primary-light-9) 0%,
        var(--el-color-primary-light-8) 100%
    );
    border: 1px solid var(--el-color-primary-light-8);
    border-radius: 6px;
    margin-bottom: 18px;
}

.imp-tpl-ic {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: var(--color-surface);
    color: var(--color-brand);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px var(--el-color-primary-light-8);
    flex-shrink: 0;
}

.imp-tpl-txt {
    flex: 1;
    min-width: 0;

    .t {
        font-size: 13px;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 2px;
    }

    .d {
        font-size: 12px;
        color: var(--color-text-tertiary);
    }
}

.imp-tpl-btns {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.imp-sec-label {
    font-size: 12.5px;
    color: var(--color-text-secondary);
    font-weight: 500;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;

    &::before {
        content: "";
        width: 2px;
        height: 10px;
        background: var(--color-brand);
        border-radius: 1px;
    }
}

/* Drag & drop upload area */
.imp-drop {
    display: block;
    border: 1px dashed var(--color-border-strong);
    border-radius: 6px;
    background: var(--color-surface-sunken);
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.15s;

    &:hover,
    &.over {
        border-color: var(--color-brand);
        background: var(--el-color-primary-light-9);
    }

    &.has-file {
        background: var(--color-surface);
        border-style: solid;
        border-color: var(--color-border);
        padding: 14px 16px;
        text-align: left;
    }
}

.imp-drop-ic {
    color: var(--color-text-disabled);
    margin-bottom: 10px;
    display: flex;
    justify-content: center;
}

.imp-drop:hover .imp-drop-ic,
.imp-drop.over .imp-drop-ic {
    color: var(--color-brand);
}

.imp-drop-t {
    font-size: 13px;
    color: var(--color-text-secondary);

    .lk {
        color: var(--color-brand);
        font-weight: 500;
    }
}

.imp-drop-d {
    font-size: 11.5px;
    color: var(--color-text-disabled);
    margin-top: 6px;
}

/* Uploaded file display */
.imp-file {
    display: flex;
    align-items: center;
    gap: 12px;
}

.imp-file-ic {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    background: var(--el-color-success-light-9);
    color: var(--color-success);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.imp-file-txt {
    flex: 1;
    min-width: 0;

    .nm {
        font-size: 13px;
        font-weight: 500;
        color: var(--gray-900);
    }

    .mt {
        font-size: 11.5px;
        color: var(--color-text-disabled);
        margin-top: 2px;
    }
}

.imp-file-x {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    background: var(--color-surface-sunken);
    color: var(--color-text-tertiary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;

    &:hover {
        background: var(--el-color-danger-light-9);
        color: var(--color-danger);
    }
}

/* Notes */
.imp-note {
    margin-top: 18px;
    padding: 12px 14px;
    background: var(--el-color-warning-light-9);
    border-radius: 4px;
    border-left: 3px solid var(--color-warning);

    .t {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--color-warning);
        margin-bottom: 6px;
    }

    ul {
        margin: 0;
        padding-left: 18px;
        font-size: 12px;
        color: var(--color-text-secondary);
        line-height: 1.8;

        li :deep(em) {
            font-style: normal;
        }
    }

    .req {
        color: var(--color-danger);
        font-style: normal;
        margin: 0 2px;
    }
}

/* Validation summary */
.imp-sum {
    display: grid;
    grid-template-columns: 2.5fr 1fr 1fr 1fr;
    gap: 1px;
    background: var(--color-divider);
    border: 1px solid var(--color-divider);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 14px;
}

.imp-sum-cell {
    background: var(--color-surface);
    padding: 10px 14px;

    .l {
        font-size: 11.5px;
        color: var(--color-text-disabled);
    }

    .v {
        font-size: 13px;
        color: var(--color-text-primary);
        font-weight: 500;
        margin-top: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;

        &.num {
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
        }
    }

    &.ok .v {
        color: var(--color-success);
    }

    &.err .v {
        color: var(--color-danger);
    }
}

/* Preview table */
.imp-tbl-wrap {
    border: 1px solid var(--color-divider);
    border-radius: 4px;
    overflow: hidden;
}

.imp-tbl-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: var(--color-surface-sunken);
    border-bottom: 1px solid var(--color-divider);
    font-size: 12.5px;

    .l {
        font-weight: 500;
        color: var(--color-text-secondary);
    }

    .r {
        display: flex;
        gap: 14px;
        font-size: 11.5px;
        color: var(--color-text-tertiary);

        i {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: 1px;
        }
    }

    .legend-ok i {
        background: var(--color-success);
    }

    .legend-err i {
        background: var(--color-danger);
    }
}

.imp-tbl-scroll {
    max-height: 260px;
    overflow: auto;
}

.imp-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;

    th {
        position: sticky;
        top: 0;
        z-index: 1;
        text-align: left;
        font-weight: 500;
        color: var(--color-text-tertiary);
        padding: 8px 12px;
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-divider);
        font-size: 11.5px;
        white-space: nowrap;
    }

    td {
        padding: 8px 12px;
        color: var(--color-text-secondary);
        border-bottom: 1px solid var(--color-divider);
        white-space: nowrap;
    }

    tr:last-child td {
        border-bottom: 0;
    }

    tr.has-err td {
        background: var(--el-color-danger-light-9);
    }

    td.rn {
        color: var(--color-text-disabled);
    }

    td.err-cell {
        color: var(--color-danger);
        text-decoration: underline wavy var(--color-danger);
        text-underline-offset: 2px;
    }
}

.dot-ok,
.dot-err {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.dot-ok {
    background: var(--color-success);
}

.dot-err {
    background: var(--color-danger);
}

.imp-err-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: var(--el-color-danger-light-9);
    border-top: 1px solid var(--color-divider);
    font-size: 12px;
    color: var(--color-danger);

    b {
        font-weight: 700;
        margin: 0 2px;
    }
}

/* Complete page */
.imp-done {
    text-align: center;
    padding: 28px 20px 12px;
}

.imp-done-ic {
    width: 66px;
    height: 66px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-success), var(--color-info));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 24px color-mix(in srgb, var(--color-success) 30%, transparent);
}

.imp-done-t {
    font-size: 18px;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 4px;
}

.imp-done-d {
    font-size: 12.5px;
    color: var(--color-text-tertiary);
    margin-bottom: 20px;
}

.imp-done-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    max-width: 320px;
    margin: 0 auto 20px;

    .cell {
        padding: 14px 10px;
        border-radius: 6px;
        background: var(--color-surface-sunken);
        border: 1px solid var(--color-divider);

        .n {
            font-size: 26px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.1;
        }

        .l {
            font-size: 12px;
            color: var(--color-text-tertiary);
            margin-top: 4px;
        }

        &.ok .n {
            color: var(--color-success);
        }

        &.err .n {
            color: var(--color-danger);
        }
    }
}

.imp-done-errors {
    text-align: left;
    max-width: 600px;
    margin: 0 auto 20px;
    padding: 12px 14px;
    background: var(--el-color-danger-light-9);
    border-radius: 6px;
    border: 1px solid var(--color-danger);

    .t {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--color-danger);
        margin-bottom: 6px;
    }

    ul {
        margin: 0;
        padding-left: 18px;
        font-size: 12px;
        color: var(--color-text-secondary);
        line-height: 1.8;
    }
}

.imp-done-acts {
    display: flex;
    gap: 8px;
    justify-content: center;
}
</style>

<style lang="scss">
/* Unscoped overrides for el-dialog */
.import-dialog {
    .el-dialog__header {
        padding: 14px 20px;
        margin: 0;
        border-bottom: 1px solid var(--color-divider);
    }

    // 高度封顶与内部滚动已由全局弹窗契约接管（theme/overrides/element-plus.scss），此处只保留自定义内边距
    .el-dialog__body {
        padding: 20px 24px 4px;
    }

    .el-dialog__footer {
        padding: 14px 20px;
        border-top: 1px solid var(--color-divider);
        background: var(--color-surface-sunken);
    }
}
</style>
