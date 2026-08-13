<template>
    <div class="decorate-hub">
        <DecorPageHeader
            :title="$t('decorateList.title')"
            :subtitle="$t('decorateList.hubSubtitle')"
        >
            <template #actions>
                <el-button @click="openMarket">{{ $t('decorateList.fromMarket') }}</el-button>
                <el-button :loading="exporting" @click="handleExport">
                    {{ $t('decorateList.exportSkin') }}
                </el-button>
                <el-button type="primary" @click="triggerImport">
                    {{ $t('decorateList.importSkin') }}
                </el-button>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".zip,application/zip"
                    class="file-input"
                    @change="onFilePicked"
                />
            </template>
        </DecorPageHeader>

        <div class="hub-grid">
            <div v-for="card in pageCards" :key="card.key" class="hub-card">
                <div class="hub-card__head">
                    <div class="hub-card__title-row">
                        <span class="hub-card__title">{{ card.label }}</span>
                        <el-tag :type="card.summary.published ? 'success' : 'info'" size="small">
                            {{
                                card.summary.published
                                    ? $t('decorateList.published')
                                    : $t('decorateList.draft')
                            }}
                        </el-tag>
                    </div>
                    <el-button type="primary" link @click="goEditor(card.key, card.label)">
                        {{ $t('decorateList.decorate') }}
                    </el-button>
                </div>
                <div v-loading="card.preview.loading" class="hub-card__preview">
                    <SimulatorPreview
                        :components="card.preview.components"
                        :selected-id="null"
                        :page-title="card.preview.title"
                        :show-header="card.showHeader"
                        :page-background="card.preview.page_settings.background_color"
                        :page-background-image="card.preview.page_settings.background_image"
                        readonly
                    />
                </div>
            </div>
        </div>

        <el-dialog
            v-model="marketVisible"
            :title="$t('decorateList.marketTitle')"
            width="640px"
            destroy-on-close
            @open="loadMarket"
        >
            <div v-loading="marketLoading" class="market-list">
                <div v-if="!marketLoading && !marketThemes.length" class="preview-hint">
                    {{ $t('decorateList.marketEmpty') }}
                </div>
                <div v-for="th in marketThemes" :key="th.code" class="market-item">
                    <div class="market-item__info">
                        <strong>{{ th.name }}</strong>
                        <span class="meta"
                            >{{ th.code }} ·
                            {{
                                th.is_free || !th.price_cents
                                    ? $t('decorateList.free')
                                    : `¥${((th.price_cents || 0) / 100).toFixed(2)}`
                            }}</span
                        >
                        <span class="meta">{{ th.summary || '' }}</span>
                    </div>
                    <el-button
                        type="primary"
                        size="small"
                        :loading="installingCode === th.code"
                        @click="installFromMarket(th.code)"
                    >
                        {{ $t('decorateList.install') }}
                    </el-button>
                </div>
            </div>
        </el-dialog>

        <el-dialog
            v-model="importVisible"
            :title="$t('decorateList.importPreviewTitle')"
            width="560px"
            destroy-on-close
        >
            <template v-if="skinPreview">
                <div class="preview-block">
                    <div class="preview-row">
                        <span class="preview-label">{{ $t('decorateList.skinName') }}</span>
                        <span
                            >{{ skinPreview.manifest.name }}（{{ skinPreview.manifest.code }} v{{
                                skinPreview.manifest.version
                            }}）</span
                        >
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">{{ $t('decorateList.pages') }}</span>
                        <span>{{
                            skinPreview.pages.map((p) => p.title || p.page_key).join('、')
                        }}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">{{ $t('decorateList.tabbar') }}</span>
                        <span
                            >{{ skinPreview.mobile.tabbar_count ?? 0 }}
                            {{ $t('decorateList.items') }}</span
                        >
                    </div>
                    <div v-if="skinPreview.missing_apps.length" class="preview-alert">
                        {{ $t('decorateList.missingApps') }}：{{
                            skinPreview.missing_apps.join(', ')
                        }}
                    </div>
                    <div v-if="skinPreview.missing_widgets.length" class="preview-alert">
                        {{ $t('decorateList.missingWidgets') }}：{{
                            skinPreview.missing_widgets.join(', ')
                        }}
                    </div>
                    <div
                        v-for="(err, i) in skinPreview.blocking_errors"
                        :key="'e' + i"
                        class="preview-alert"
                    >
                        {{ err }}
                    </div>
                    <div
                        v-for="(w, i) in skinPreview.warnings"
                        :key="'w' + i"
                        class="preview-warn"
                    >
                        {{ w }}
                    </div>
                    <div class="preview-hint">{{ $t('decorateList.applyHint') }}</div>
                </div>
            </template>
            <template #footer>
                <el-button @click="importVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="applying"
                    :disabled="!skinPreview?.ok"
                    @click="handleApply"
                >
                    {{ $t('decorateList.applySkin') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import {
    diyApi,
    type DiyHomeSummary,
    type MarketThemeItem,
    type SkinImportPreview
} from '@/api/diy'
import { t } from '@/utils/i18n'

import DecorPageHeader from './components/DecorPageHeader.vue'
import SimulatorPreview from './editor/SimulatorPreview.vue'

type PageKey = 'home' | 'member'

interface PagePreview {
    loading: boolean
    components: any[]
    page_settings: Record<string, any>
    title: string
}

const router = useRouter()

const home = reactive<DiyHomeSummary>({
    title: '',
    published: false,
    component_count: 0,
    updated_at: null
})

const member = reactive<DiyHomeSummary>({
    title: '',
    published: false,
    component_count: 0,
    updated_at: null
})

const homePreview = reactive<PagePreview>({
    loading: false,
    components: [],
    page_settings: {},
    title: ''
})

const memberPreview = reactive<PagePreview>({
    loading: false,
    components: [],
    page_settings: {},
    title: ''
})

const exporting = ref(false)
const applying = ref(false)
const importVisible = ref(false)
const skinPreview = ref<SkinImportPreview | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const marketVisible = ref(false)
const marketLoading = ref(false)
const marketThemes = ref<MarketThemeItem[]>([])
const installingCode = ref('')

const pageCards = computed(() => [
    {
        key: 'home' as PageKey,
        label: t('decorateList.home'),
        summary: home,
        preview: homePreview,
        showHeader: homePreview.page_settings.show_header !== false
    },
    {
        key: 'member' as PageKey,
        label: t('decorateList.member'),
        summary: member,
        preview: memberPreview,
        showHeader: false
    }
])

onMounted(() => {
    refreshSummaries()
    void Promise.all([loadPreview('home'), loadPreview('member')])
})

function previewState(key: PageKey): PagePreview {
    return key === 'home' ? homePreview : memberPreview
}

function pageLabel(key: PageKey): string {
    return key === 'home' ? t('decorateList.home') : t('decorateList.member')
}

function refreshSummaries() {
    diyApi
        .getHomeSummary()
        .then((res) => Object.assign(home, res.data))
        .catch(() => {})
    diyApi
        .getPageSummary('member')
        .then((res) => Object.assign(member, res.data))
        .catch(() => {})
}

async function loadPreview(key: PageKey) {
    const state = previewState(key)
    state.loading = true
    try {
        const res = await diyApi.getPageDraft(key)
        state.components = res.data?.components || []
        state.page_settings = res.data?.page_settings || {}
        state.title =
            key === 'member' ? t('decorateList.member') : res.data?.title || t('decorateList.home')
    } catch {
        state.components = []
        state.page_settings = {}
        state.title = pageLabel(key)
    } finally {
        state.loading = false
    }
}

function reloadPreviews() {
    void Promise.all([loadPreview('home'), loadPreview('member')])
}

function goEditor(key: string, title: string) {
    router.push({ path: '/diy/editor', query: { key, title } })
}

async function handleExport() {
    exporting.value = true
    try {
        const blob = await diyApi.exportSkin({ include_custom: true })
        if (!(blob instanceof Blob)) {
            ElMessage.error(t('decorateList.exportFailed'))
            return
        }
        if (blob.type.includes('application/json')) {
            const text = await blob.text()
            try {
                const json = JSON.parse(text)
                ElMessage.error(json.message || t('decorateList.exportFailed'))
            } catch {
                ElMessage.error(t('decorateList.exportFailed'))
            }
            return
        }
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `skin-${Date.now()}.zip`
        a.click()
        URL.revokeObjectURL(url)
        ElMessage.success(t('decorateList.exportSuccess'))
    } finally {
        exporting.value = false
    }
}

function triggerImport() {
    fileInput.value?.click()
}

async function onFilePicked(ev: Event) {
    const input = ev.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    try {
        const res = await diyApi.importSkin(file)
        skinPreview.value = res.data
        importVisible.value = true
    } catch {
        // 错误已由拦截器提示
    }
}

async function handleApply() {
    if (!skinPreview.value?.token) return
    applying.value = true
    try {
        const res = await diyApi.applySkin(skinPreview.value.token)
        ElMessage.success(res.data?.hint || t('decorateList.applySuccess'))
        importVisible.value = false
        skinPreview.value = null
        refreshSummaries()
        reloadPreviews()
    } finally {
        applying.value = false
    }
}

function openMarket() {
    marketVisible.value = true
}

async function loadMarket() {
    marketLoading.value = true
    try {
        const res = await diyApi.listMarketThemes({ page: 1, limit: 24 })
        marketThemes.value = res.data?.list ?? []
    } catch {
        marketThemes.value = []
    } finally {
        marketLoading.value = false
    }
}

async function installFromMarket(code: string) {
    installingCode.value = code
    try {
        const res = await diyApi.installMarketTheme({ code, auto_apply: true })
        const applied = (res.data as any)?.applied
        if (applied) {
            ElMessage.success(applied.hint || t('decorateList.applySuccess'))
            marketVisible.value = false
            refreshSummaries()
            reloadPreviews()
        } else {
            const p = (res.data as any)?.preview as SkinImportPreview | undefined
            if (p?.token) {
                skinPreview.value = p
                marketVisible.value = false
                importVisible.value = true
            } else {
                ElMessage.warning(t('decorateList.installPreviewOnly'))
            }
        }
    } finally {
        installingCode.value = ''
    }
}
</script>

<style scoped lang="scss">
.decorate-hub {
    padding: 0 4px 20px;
}

.file-input {
    display: none;
}

.hub-grid {
    display: grid;
    // 与改造前预览列一致：固定上限 420px，不随剩余空间均分拉宽
    grid-template-columns: repeat(2, minmax(320px, 420px));
    gap: 14px;
    align-items: start;
    justify-content: start;
}

@media (max-width: 960px) {
    .hub-grid {
        grid-template-columns: minmax(320px, 420px);
    }
}

.hub-card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-hairline);
    padding: 14px 14px 18px;
    min-width: 0;
}

.hub-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.hub-card__title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.hub-card__title {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary);
}

.hub-card__preview {
    display: flex;
    justify-content: center;
    min-height: 520px;
    transform: scale(0.92);
    transform-origin: top center;
}

.preview-block {
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-size: 13px;
    color: var(--color-text-primary);
}

.preview-row {
    display: flex;
    gap: 12px;
}

.preview-label {
    flex: 0 0 72px;
    color: var(--color-text-tertiary);
}

.preview-alert {
    color: var(--el-color-danger);
}

.preview-warn {
    color: var(--el-color-warning);
}

.preview-hint {
    margin-top: 4px;
    color: var(--color-text-secondary);
    line-height: 1.5;
}

.market-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 120px;
}

.market-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--color-divider);
    border-radius: var(--radius-lg);
}

.market-item__info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.market-item__info .meta {
    font-size: 12px;
    color: var(--color-text-tertiary);
}
</style>
