<template>
    <div class="diy-editor-page">
        <div class="editor-topbar">
            <div class="topbar-left">
                <el-button text class="back-btn" @click="handleBack">
                    <el-icon><ArrowLeft /></el-icon>
                    {{ $t('diyEditor.back') }}
                </el-button>
                <span class="page-name">{{ displayTitle }}</span>
            </div>
            <div class="topbar-right">
                <el-button :disabled="!editor.undoStack.value.length" @click="editor.undo">
                    <el-icon><RefreshLeft /></el-icon>
                    {{ $t('diyEditor.undo') }}
                </el-button>
                <el-button :disabled="!editor.redoStack.value.length" @click="editor.redo">
                    <el-icon><RefreshRight /></el-icon>
                    {{ $t('diyEditor.redo') }}
                </el-button>
                <el-button @click="versionVisible = true">
                    <el-icon><Clock /></el-icon>
                    {{ $t('diyEditor.versions') }}
                </el-button>
                <el-button :loading="saving" :disabled="!loaded" @click="handleSave">
                    <el-icon><Document /></el-icon>
                    {{ $t('diyEditor.save') }}
                </el-button>
                <el-button type="primary" :disabled="!loaded" :loading="publishing" @click="handlePublish">
                    <el-icon><Upload /></el-icon>
                    {{ $t('diyEditor.publish') }}
                </el-button>
            </div>
        </div>
        <div class="editor-body">
            <div class="panel-left"><ComponentPanel :page-key="pageKey" @add="onAdd" /></div>
            <div class="panel-center">
                <SimulatorPreview
                    :components="editor.components.value"
                    :selected-id="editor.selectedId.value"
                    :page-title="editor.pageTitle.value"
                    :page-settings-active="editor.pageSettingsActive.value"
                    :show-header="showHeaderInPreview"
                    :page-background="editor.pageSettings.value.background_color"
                    :page-background-image="editor.pageSettings.value.background_image"
                    @select="editor.select"
                    @move="editor.move"
                    @remove="editor.remove"
                    @reorder="onReorder"
                    @duplicate="onDuplicate"
                    @toggle-hidden="editor.toggleHidden"
                    @activate-page-settings="editor.activatePageSettings"
                />
            </div>
            <div class="panel-right">
                <PropertyPanel
                    :component="editor.selected.value"
                    :page-key="pageKey"
                    @begin="editor.beginChange"
                />
            </div>
        </div>
        <VersionDrawer v-model="versionVisible" :page-key="pageKey" @restored="reload" />

        <el-dialog
            v-model="publishVisible"
            :title="t('diyEditor.publishDialogTitle')"
            width="440px"
            class="dlg-sm"
            destroy-on-close
            @closed="publishNote = ''"
        >
            <el-form label-position="top" @submit.prevent>
                <el-form-item :label="t('diyEditor.publishVersionNo')">
                    <el-input :model-value="String(nextVersionNo)" disabled />
                </el-form-item>
                <el-form-item :label="t('diyEditor.publishNote')" required>
                    <el-input
                        v-model="publishNote"
                        maxlength="255"
                        show-word-limit
                        :placeholder="t('diyEditor.publishNotePlaceholder')"
                        @keyup.enter="confirmPublish"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="publishVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="publishing" @click="confirmPublish">
                    {{ $t('diyEditor.publishConfirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>
<script setup lang="ts">
import { ArrowLeft, Clock, Document, RefreshLeft, RefreshRight, Upload } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'

import { diyApi } from '@/api/diy'

import ComponentPanel from './editor/ComponentPanel.vue'
import PropertyPanel from './editor/PropertyPanel.vue'
import SimulatorPreview from './editor/SimulatorPreview.vue'
import { useEditor } from './editor/useEditor'
import { usePluginWidgets } from './editor/usePluginWidgets'
import VersionDrawer from './editor/VersionDrawer.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const pageKey = computed(() => (route.query.key as string) || 'home')
const editor = useEditor()
const displayTitle = computed(() => {
    if (pageKey.value === 'member') return t('decorateList.member')
    if (editor.pageTitle.value) return editor.pageTitle.value
    if (pageKey.value === 'home') return t('diyEditor.homeTitle')
    return (typeof route.query.title === 'string' && route.query.title) || pageKey.value
})
/** 编辑器始终显示标题栏以便点击进入页面设置；show_header 仅作用于 C 端 */
const showHeaderInPreview = computed(() => pageKey.value !== 'member')

function normalizeMemberSettings(settings: Record<string, any> = {}) {
    const next: Record<string, any> = { ...settings, show_header: false }
    delete next.popup_ad
    return next
}
const { metaOf } = usePluginWidgets()
function onAdd(type: string) {
    const meta = metaOf(type)
    const res = editor.addWidget(type, meta ? structuredClone(meta.default_props) : undefined)
    if (res && res.ok === false && res.reason === 'search-banner-exists') {
        ElMessage.warning('本页已有「轮播搜索」，每页仅可添加一个')
    }
}

function onDuplicate(id: string) {
    const res = editor.duplicate(id)
    if (res && res.ok === false && res.reason === 'search-banner-exists') {
        ElMessage.warning('「轮播搜索」每页仅可有一个，无法复制')
    }
}
const loaded = ref(false)
const saving = ref(false)
const publishing = ref(false)
const versionVisible = ref(false)
const publishVisible = ref(false)
const publishNote = ref('')
const nextVersionNo = ref(1)

// ───── 未保存修改守卫 ─────
const savedSnapshot = ref('')
const currentSnapshot = () =>
    JSON.stringify({
        components: editor.components.value,
        pageSettings: editor.pageSettings.value,
        pageTitle: editor.pageTitle.value
    })
const isDirty = () => loaded.value && currentSnapshot() !== savedSnapshot.value

async function reload() {
    try {
        const res = await diyApi.getPageDraft(pageKey.value)
        const isMember = pageKey.value === 'member'
        const fallbackTitle = isMember
            ? t('decorateList.member')
            : pageKey.value === 'home'
              ? t('diyEditor.homeTitle')
              : (typeof route.query.title === 'string' && route.query.title) || pageKey.value
        const settings = isMember
            ? normalizeMemberSettings(res.data?.page_settings || {})
            : res.data?.page_settings || {}
        const title = isMember ? t('decorateList.member') : res.data?.title || fallbackTitle
        editor.setState(res.data?.components || [], settings, title)
        loaded.value = true
        savedSnapshot.value = currentSnapshot()
    } catch {
        // 错误已由响应拦截器提示；保持 loaded=false 禁用保存，避免空数据覆盖
    }
}
onMounted(reload)
watch(pageKey, () => reload())

function onReorder(ids: string[]) {
    editor.reorder(ids)
}

function draftPayload() {
    const isMember = pageKey.value === 'member'
    return {
        components: editor.components.value,
        page_settings: isMember
            ? normalizeMemberSettings(editor.pageSettings.value as Record<string, any>)
            : editor.pageSettings.value,
        title: isMember ? t('decorateList.member') : editor.pageTitle.value
    }
}

async function handleSave() {
    if (!loaded.value) return
    saving.value = true
    try {
        await diyApi.savePageDraft(pageKey.value, draftPayload())
        if (pageKey.value === 'member') {
            editor.setState(
                editor.components.value,
                normalizeMemberSettings(editor.pageSettings.value as Record<string, any>),
                t('decorateList.member')
            )
        }
        ElMessage.success(t('diyEditor.saved'))
        savedSnapshot.value = currentSnapshot()
    } finally {
        saving.value = false
    }
}
async function handlePublish() {
    if (!loaded.value) return
    publishNote.value = ''
    nextVersionNo.value = 1
    publishVisible.value = true
    try {
        const res = await diyApi.listPageVersions(pageKey.value)
        const maxNo = Math.max(0, ...(res.data || []).map((v) => Number(v.version_no) || 0))
        nextVersionNo.value = maxNo + 1
    } catch {
        // 预览失败仍允许发布；实际 version_no 以服务端为准
    }
}

async function confirmPublish() {
    const note = publishNote.value.trim()
    if (!note) {
        ElMessage.warning(t('diyEditor.publishNoteRequired'))
        return
    }
    if (!loaded.value) return
    publishing.value = true
    try {
        await diyApi.savePageDraft(pageKey.value, draftPayload())
        await diyApi.publishPage(pageKey.value, { note })
        ElMessage.success(t('diyEditor.published'))
        savedSnapshot.value = currentSnapshot()
        publishVisible.value = false
    } finally {
        publishing.value = false
    }
}

onBeforeRouteLeave(async () => {
    if (!isDirty()) return true
    try {
        await ElMessageBox.confirm(t('diyEditor.unsavedLeave'), t('diyEditor.unsavedTitle'), {
            type: 'warning',
            confirmButtonText: t('diyEditor.leave'),
            cancelButtonText: t('common.cancel')
        })
        return true
    } catch {
        return false
    }
})

function onBeforeUnload(e: BeforeUnloadEvent) {
    if (isDirty()) e.preventDefault()
}
onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

function handleBack() {
    router.push('/diy/home') // 页面装修列表（路由 path 未变）
}
</script>
<style scoped lang="scss">
.diy-editor-page {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: var(--color-bg-page);
}

.editor-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px; // 对齐 Shop 编辑器（66px 是 layout 外层头部，不是这里）
    padding: 0 16px;
    background: var(--color-brand);
    color: #fff;
    flex: none;

    // 按钮图标与文字间距（EP 只对 el-icon+span 自动加距，裸文本节点不生效）
    :deep(.el-button .el-icon) {
        margin-right: 4px;
    }

    .topbar-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .page-name {
        font-size: 15px;
        font-weight: 600;
    }
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    // 返回等 text 按钮
    :deep(.el-button.is-text) {
        color: #fff;
        &:hover,
        &:focus {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }
    }
    // 撤销/重做/历史/保存：白色透明边框幽灵按钮
    :deep(.el-button:not(.is-text):not(.el-button--primary)) {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.35);
        color: #fff;
        &:hover:not(.is-disabled) {
            background: rgba(255, 255, 255, 0.22);
            border-color: rgba(255, 255, 255, 0.55);
            color: #fff;
        }
        &.is-disabled {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.45);
        }
    }
    // 发布：反白实心（品牌字用 tenant 主色）
    :deep(.el-button--primary) {
        background: #fff;
        border-color: #fff;
        color: var(--color-brand);
        &:hover:not(.is-disabled) {
            background: #fff;
            color: var(--color-brand-active);
            opacity: 0.92;
        }
        &.is-disabled {
            background: rgba(255, 255, 255, 0.6);
            color: color-mix(in srgb, var(--color-brand) 55%, transparent);
        }
    }
}

.editor-body {
    flex: 1;
    display: flex;
    min-height: 0;

    .panel-left {
        width: 220px;
        flex: none;
        background: var(--color-surface);
        border-right: 1px solid var(--color-divider);
        overflow: auto;
    }
    .panel-center {
        // 对齐 Shop：中栏不滚动，手机固定高度居中，滚动发生在机身内部（.sim-list）
        flex: 1;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: var(--color-bg-page);
    }
    .panel-right {
        width: 400px;
        flex: none;
        background: var(--color-surface);
        border-left: 1px solid var(--color-divider);
        overflow: auto;
    }
}
</style>
