<template>
    <div class="decor-page">
        <DecorPageHeader title="打包发布" subtitle="按租户独立编译移动端产物：写入页面集合与首屏兜底配置；装修/主题/底部导航/启动入口保存后即可刷新生效">
            <template #actions>
                <el-button @click="reload()" :loading="listLoading">刷新</el-button>
            </template>
        </DecorPageHeader>

        <DecorSection title="多端发布">
            <div class="pack-grid">
                <div
                    v-for="pf in PLATFORMS"
                    :key="pf.id"
                    class="pack-card"
                    :class="{ 'pack-card--off': !pf.supported }"
                >
                    <div class="pack-card__head">
                        <div class="pack-icon" :style="{ background: pf.color }">
                            <i :class="`i-svg:${pf.icon}`" class="pack-glyph" />
                        </div>
                        <span class="pack-card__name">{{ pf.name }}</span>
                        <el-tag
                            v-if="pf.supported"
                            size="small"
                            :type="latestByPlatform[pf.id] ? statusTagType(latestByPlatform[pf.id].status) : 'info'"
                        >{{ platformCardLabel(pf.id) }}</el-tag>
                        <el-tag v-else size="small" type="info">未支持</el-tag>
                    </div>
                    <div class="pack-card__note">
                        {{ pf.supported ? platformCardNote(pf.id) : '暂未开通该端构建' }}
                    </div>
                    <div v-if="pf.supported" class="pack-card__foot">
                        <el-button
                            type="primary"
                            size="small"
                            :loading="!!triggerLoading[pf.id]"
                            :disabled="isPlatformInflight(pf.id)"
                            @click="trigger(pf.id as BuildPlatform)"
                        >
                            {{ isPlatformInflight(pf.id) ? '构建中' : '触发构建' }}
                        </el-button>
                        <el-button
                            v-if="inflightIdByPlatform[pf.id]"
                            size="small"
                            type="danger"
                            plain
                            :loading="!!cancelLoading[inflightIdByPlatform[pf.id]!]"
                            @click="cancelBuild(inflightIdByPlatform[pf.id]!)"
                        >取消</el-button>
                        <el-button v-if="pf.id === 'mp-weixin'" size="small" @click="openWechatConfig">配置</el-button>
                    </div>
                </div>
            </div>
        </DecorSection>

        <DecorSection title="构建记录">
            <el-alert
                v-if="hasQueued"
                type="warning"
                :closable="false"
                class="build-worker-alert"
                title="存在排队中的构建：请确认 mobile-builds 队列 worker 已启动；本地可执行 make queue-mobile，Docker 部署需确认 mobile-build-worker 为 RUNNING。"
            />
            <el-table :data="rows" :loading="listLoading">
                <el-table-column prop="build_no" label="编号" width="140" />
                <el-table-column prop="platform" label="平台" width="100" />
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" />
                <el-table-column label="操作" width="320">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="openDetail(row as MobileBuild)">详情</el-button>
                        <el-button
                            v-if="row.status === 0 || row.status === 1"
                            link
                            type="danger"
                            :loading="!!cancelLoading[row.id]"
                            @click="cancelBuild(row.id)"
                        >取消</el-button>
                        <el-button
                            v-if="row.status === 0"
                            link
                            type="primary"
                            @click="requeue(row.id)"
                        >重投递</el-button>
                        <el-button
                            v-if="row.platform === 'h5' && row.status === 2"
                            link
                            type="primary"
                            @click="release(row.id)"
                        >发布</el-button>
                        <el-button
                            v-if="row.platform === 'mp-weixin' && (row.status === 2 || row.status === 3 || row.status === 4)"
                            link
                            type="primary"
                            :loading="!!uploadLoading[row.id]"
                            :disabled="isUploading"
                            @click="upload(row.id)"
                        >{{ row.status === 2 ? '上传' : '重新上传' }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination
                background
                layout="prev, pager, next, total"
                :page-size="limit"
                :current-page="page"
                :total="total"
                class="build-pagination"
                @update:current-page="onPageChange"
            />
        </DecorSection>

        <!-- 详情 dialog -->
        <el-dialog v-model="detailDialogVisible" title="构建详情" class="dlg-md" @close="current = null">
            <div v-if="current">
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="编号">{{ current.build_no }}</el-descriptions-item>
                    <el-descriptions-item label="平台">{{ current.platform }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTagType(current.status)">{{ statusLabel(current.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="开始">{{ current.started_at || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="结束">{{ current.finished_at || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="产物路径">{{ current.artifact_path || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="Driver">{{ current.driver || '—' }}</el-descriptions-item>
                    <el-descriptions-item v-if="current.remote_job_id" label="远程任务">{{ current.remote_job_id }}</el-descriptions-item>
                    <el-descriptions-item label="工作目录">{{ current.work_dir || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="预期产物">{{ current.artifact_hint || '—' }}</el-descriptions-item>
                    <el-descriptions-item v-if="current.release_url" label="H5访问">
                        <el-link type="primary" :href="current.release_url" target="_blank">
                            {{ current.release_url }}
                        </el-link>
                    </el-descriptions-item>
                </el-descriptions>
                <div v-if="current.error_log" class="log-block">
                    <div class="log-title">构建日志（节选）</div>
                    <pre>{{ current.error_log.slice(0, 4000) }}</pre>
                </div>
                <div v-if="current.upload_result_json" class="log-block">
                    <div class="log-title">上传结果</div>
                    <pre>{{ JSON.stringify(current.upload_result_json, null, 2) }}</pre>
                </div>
            </div>
            <template v-if="current && (current.status === 0 || current.status === 1)" #footer>
                <el-button
                    type="danger"
                    :loading="!!cancelLoading[current.id]"
                    @click="cancelBuild(current.id)"
                >取消构建</el-button>
                <el-button @click="detailDialogVisible = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 小程序上传配置 dialog -->
        <el-dialog v-model="keyDialogVisible" title="微信小程序上传配置" class="dlg-sm">
            <el-form label-position="top" class="decor-form">
                <el-form-item label="小程序 AppID">
                    <el-input v-model="wechatAppid" placeholder="请输入微信小程序 AppID" maxlength="64" />
                </el-form-item>
                <el-form-item label="上传版本号">
                    <el-input
                        v-model="wechatUploadVersion"
                        placeholder="如 1.0.3"
                        maxlength="32"
                    />
                    <div class="field-hint">语义化版本 x.y.z；每次上传成功后自动 patch +1（1.0.3 → 1.0.4）</div>
                </el-form-item>
                <el-form-item label="项目备注">
                    <el-input
                        v-model="wechatUploadDesc"
                        type="textarea"
                        :rows="2"
                        placeholder="租户后台发布"
                        maxlength="200"
                        show-word-limit
                    />
                    <div class="field-hint">显示在微信公众平台「开发版本」备注中，请填写可读说明</div>
                </el-form-item>
                <el-form-item label="上传私钥">
                    <el-upload :auto-upload="false" :on-change="onKeyFileChange" :limit="1" accept=".key,.pem,.txt">
                        <el-button type="primary">选择 .key 文件</el-button>
                    </el-upload>
                </el-form-item>
            </el-form>
            <div class="key-tip">
                AppID、版本号、备注与私钥用于 miniprogram-ci 上传开发版；私钥经 AES-256 加密保存。CI 机器人沿用微信默认编号。
            </div>
            <template #footer>
                <el-button type="danger" link @click="clearKey">清除已存私钥</el-button>
                <el-button type="primary" :loading="savingWechatConfig" @click="saveWechatConfig">保存配置</el-button>
                <el-button @click="keyDialogVisible = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    mobileBuildApi,
    type MobileBuild,
    type BuildPlatform,
    type BuildStatus,
} from '@/api/mobile-build'
import { mobileConfigApi } from '@/api/mobile-config'
import feedback from '@/utils/feedback'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const rows = ref<MobileBuild[]>([])
const total = ref(0)
const page = ref(1)
const limit = 20
const listLoading = ref(false)
const triggerLoading = reactive<Record<string, boolean>>({})
const cancelLoading = reactive<Record<number, boolean>>({})
const uploadLoading = reactive<Record<number, boolean>>({})
const isUploading = computed(() => Object.values(uploadLoading).some(Boolean))
const current = ref<MobileBuild | null>(null)
const detailDialogVisible = ref(false)
const keyDialogVisible = ref(false)
const wechatAppid = ref('')
const wechatUploadVersion = ref('1.0.0')
const wechatUploadDesc = ref('租户后台发布')
const savingWechatConfig = ref(false)

// icon 为 src/assets/icons 下的彩色品牌 svg（原色渲染），color 是图标圆底：
// 彩色图标配浅色底；ios 是纯白 logo，保留 Apple 品牌黑底。
// 注意：微信公众号内打开的就是 H5 产物（同一端），不单设公众号卡片。
const PLATFORMS: { id: string; name: string; supported: boolean; icon: string; color: string }[] = [
    { id: 'h5', name: 'H5 / 微信公众号', supported: true, icon: 'html5', color: '#f5f7fa' },
    { id: 'mp-weixin', name: '微信小程序', supported: true, icon: 'weapp', color: '#f5f7fa' },
    { id: 'pc', name: 'PC 门户', supported: false, icon: 'google-chrome', color: '#f5f7fa' },
    { id: 'android', name: 'Android App', supported: false, icon: 'android', color: '#f5f7fa' },
    { id: 'ios', name: 'iOS App', supported: false, icon: 'ios', color: '#11151C' },
    { id: 'douyin', name: '抖音小程序', supported: false, icon: 'douyin', color: '#f5f7fa' },
]

// 最新状态聚合：基于已加载构建行（分页首页）；历史很长时可能非绝对最新，务实取舍。
const latestByPlatform = computed<Record<string, MobileBuild>>(() => {
    const map: Record<string, MobileBuild> = {}
    for (const r of rows.value) {
        const prev = map[r.platform]
        if (!prev || r.created_at > prev.created_at) map[r.platform] = r
    }
    return map
})

const inflightIdByPlatform = computed<Record<string, number | undefined>>(() => {
    const map: Record<string, number | undefined> = {}
    for (const r of rows.value) {
        if (r.status !== 0 && r.status !== 1) continue
        const prevId = map[r.platform]
        if (prevId === undefined) {
            map[r.platform] = r.id
            continue
        }
        const prev = rows.value.find((x) => x.id === prevId)
        if (!prev || r.created_at > prev.created_at) map[r.platform] = r.id
    }
    return map
})

const hasQueued = computed(() => rows.value.some((r) => r.status === 0))

const STATUS_LABEL: Record<BuildStatus, string> = {
    0: '排队',
    1: '构建中',
    2: '成功',
    3: '失败',
    4: '已上传',
    5: '已发布',
}

const STATUS_TAG: Record<BuildStatus, 'info' | 'warning' | 'success' | 'danger' | 'primary'> = {
    0: 'info',
    1: 'warning',
    2: 'success',
    3: 'danger',
    4: 'primary',
    5: 'success',
}

function statusLabel(s: BuildStatus) {
    return STATUS_LABEL[s] ?? String(s)
}

function statusTagType(s: BuildStatus) {
    return STATUS_TAG[s] ?? 'info'
}

function isPlatformInflight(platform: string): boolean {
    return inflightIdByPlatform.value[platform] !== undefined
}

function platformCardLabel(platform: string): string {
    const inflightId = inflightIdByPlatform.value[platform]
    if (inflightId !== undefined) {
        const row = rows.value.find((r) => r.id === inflightId)
        return row ? statusLabel(row.status) : '构建中'
    }
    const latest = latestByPlatform.value[platform]
    return latest ? statusLabel(latest.status) : '未构建'
}

function platformCardNote(platform: string): string {
    const inflightId = inflightIdByPlatform.value[platform]
    if (inflightId !== undefined) {
        const row = rows.value.find((r) => r.id === inflightId)
        if (row?.status === 0) return '排队中，可取消后重新触发'
        return '构建进行中…'
    }
    const latest = latestByPlatform.value[platform]
    return latest ? '最近构建 ' + latest.created_at : '尚未构建'
}

async function reload(opts: { silent?: boolean } = {}) {
    if (!opts.silent) listLoading.value = true
    try {
        const res = await mobileBuildApi.list({ page: page.value, limit })
        rows.value = res.data.items
        total.value = res.data.total
    } finally {
        if (!opts.silent) listLoading.value = false
    }
}

function onPageChange(p: number) {
    page.value = p
    reload()
}

async function openDetail(row: MobileBuild) {
    const res = await mobileBuildApi.detail(row.id)
    current.value = res.data
    detailDialogVisible.value = true
}

async function trigger(platform: BuildPlatform) {
    if (isPlatformInflight(platform)) return
    if (
        !(await ElMessageBox.confirm(
            `触发 ${platform} 构建？\n\n独立编译会打包页面集合与插件分包，并写入首屏兜底配置。\n页面装修发布、主题风格、底部导航文案/图标/颜色、启动与首页等软配置保存后，用户刷新即可生效，无需为此单独重建。\n若底部导航挂上当前包中不存在的新页面，仍需重新打包。`,
            '提示',
            { dangerouslyUseHTMLString: false },
        ).catch(() => false))
    ) {
        return
    }
    triggerLoading[platform] = true
    try {
        const res = await mobileBuildApi.create(platform)
        ElMessage.success(`构建已入队：${res.data.build_no}`)
        await reload({ silent: true })
        ensurePolling()
    } finally {
        triggerLoading[platform] = false
    }
}

async function cancelBuild(id: number) {
    if (
        !(await ElMessageBox.confirm('确认取消该构建？取消后状态记为失败，可重新触发。', '取消构建').catch(
            () => false,
        ))
    ) {
        return
    }
    cancelLoading[id] = true
    try {
        const res = await mobileBuildApi.cancel(id)
        ElMessage.success('已取消构建')
        if (current.value?.id === id) {
            current.value = res.data
        }
        await reload({ silent: true })
        if (!hasInflight()) stopPolling()
    } finally {
        cancelLoading[id] = false
    }
}

// v2.6.0：构建异步执行，列表里出现 queued/running 时启动 3 秒轮询，
// 全部走完后自动停。轮询使用静默刷新，避免触发按钮闪烁。
const POLL_INTERVAL_MS = 3000
let pollTimer: ReturnType<typeof setInterval> | null = null

function hasInflight(): boolean {
    return rows.value.some((r) => r.status === 0 || r.status === 1)
}

function ensurePolling() {
    if (pollTimer) return
    pollTimer = setInterval(async () => {
        await reload({ silent: true })
        if (current.value && (current.value.status === 0 || current.value.status === 1)) {
            const fresh = await mobileBuildApi.detail(current.value.id)
            current.value = fresh.data
        }
        if (!hasInflight()) {
            stopPolling()
        }
    }, POLL_INTERVAL_MS)
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

async function release(id: number) {
    const res = await mobileBuildApi.release(id)
    ElMessage.success('H5 已发布')
    current.value = res.data
    reload({ silent: true })
}

async function requeue(id: number) {
    await mobileBuildApi.requeue(id)
    ElMessage.success('已重新投递到 mobile-builds 队列')
    await reload({ silent: true })
    ensurePolling()
}

async function upload(id: number) {
    if (isUploading.value) return
    uploadLoading[id] = true
    feedback.loading('正在上传小程序开发版，请稍候…')
    try {
        const res = await mobileBuildApi.upload(id)
        const ver = res.data?.upload_result_json?.version
        ElMessage.success(ver ? `小程序已上传开发版（${ver}）` : '小程序已上传开发版')
        await reload({ silent: true })
    } finally {
        feedback.closeLoading()
        uploadLoading[id] = false
    }
}

async function openWechatConfig() {
    const res = await mobileConfigApi.get()
    wechatAppid.value = res.data.wechat_appid || ''
    wechatUploadVersion.value = res.data.wechat_upload_version || '1.0.0'
    wechatUploadDesc.value = res.data.wechat_upload_desc || '租户后台发布'
    keyDialogVisible.value = true
}

async function saveWechatConfig() {
    const version = wechatUploadVersion.value.trim()
    if (!/^\d+\.\d+\.\d+$/.test(version)) {
        ElMessage.warning('版本号须为语义化格式，如 1.0.3')
        return
    }
    savingWechatConfig.value = true
    try {
        await mobileConfigApi.update({
            wechat_appid: wechatAppid.value.trim(),
            wechat_upload_version: version,
            wechat_upload_desc: wechatUploadDesc.value.trim() || '租户后台发布',
        })
        ElMessage.success('小程序配置已保存')
    } finally {
        savingWechatConfig.value = false
    }
}

async function onKeyFileChange(file: { raw?: File }) {
    if (!file?.raw) return
    await mobileBuildApi.saveWechatKey(file.raw)
    ElMessage.success('私钥已加密保存')
}

async function clearKey() {
    if (
        !(await ElMessageBox.confirm('确认清除已存私钥？此后无法上传小程序，直到重新上传。').catch(
            () => false,
        ))
    ) {
        return
    }
    await mobileBuildApi.clearWechatKey()
    ElMessage.success('已清除')
}

onMounted(async () => {
    await reload()
    if (hasInflight()) {
        ensurePolling()
    }
})

onUnmounted(stopPolling)
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.pack-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.pack-card {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-height: 110px;
}
.pack-card--off {
    opacity: 0.55;
    pointer-events: none;
    cursor: not-allowed;
}
.pack-card__head {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pack-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-xl);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--color-border);
}
/* 品牌 svg 原色渲染（uno FileSystemIconLoader：svg 自带 fill 时不做 currentColor mask），无需着色 */
.pack-glyph {
    font-size: 24px;
}
.pack-card__name {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary);
}
.pack-card__note {
    font-size: 11.5px;
    color: var(--color-text-tertiary);
}
.pack-card__foot {
    margin-top: auto;
    display: flex;
    gap: 8px;
}

.build-pagination {
    margin-top: 12px;
}

.build-worker-alert {
    margin-bottom: 12px;
}

.key-tip {
    margin-top: 10px;
    font-size: 11.5px;
    color: var(--color-text-tertiary);
    line-height: 1.6;
}
.field-hint {
    margin-top: 4px;
    font-size: 11.5px;
    color: var(--color-text-tertiary);
    line-height: 1.5;
}
.log-block {
    margin-top: 12px;
}
.log-title {
    font-size: 13px;
    color: var(--color-text-tertiary);
    margin-bottom: 4px;
}
.log-block pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 240px;
    overflow: auto;
    white-space: pre-wrap;
}
</style>
