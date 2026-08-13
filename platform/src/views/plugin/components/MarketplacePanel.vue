<script setup lang="ts">
import { ElAlert, ElButton, ElEmpty, ElMessage, ElMessageBox, ElPagination } from 'element-plus'
import { onMounted, ref } from 'vue'

import {
    type InstallResolution,
    marketplaceApi,
    type MarketplaceAppRow,
    type MarketplaceCategory,
    type MarketplaceConnection
} from '@/api/marketplace'
import { useMarketplaceInstall } from '@/composables/useMarketplaceInstall'

import InstallConfirmDialog from './InstallConfirmDialog.vue'
import MarketplaceAppCard from './MarketplaceAppCard.vue'
import MarketplaceConnectDialog from './MarketplaceConnectDialog.vue'

// 安装成功后通知父组件刷新「本地插件」列表并切换 tab
const emit = defineEmits<{ (e: 'installed'): void }>()

const conn = ref<MarketplaceConnection | null>(null)
const rows = ref<MarketplaceAppRow[]>([])
const categories = ref<MarketplaceCategory[]>([])
const selectedCategoryId = ref<number>(0)
const isPublic = ref(false)
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const syncing = ref(false)
const busy = ref(false)

// 连接账号 / 安装确认 两个对话框
const connectVis = ref(false)
const connectAppCode = ref<string | null>(null)
const connectAppName = ref<string>('')
const confirmVis = ref(false)
const resolution = ref<InstallResolution | null>(null)
// 触发安装的列表行：名称/图标以它为准传给确认弹窗（install-intent 精简结果常缺这两项）
const selectedRow = ref<MarketplaceAppRow | null>(null)

const { resolveDirect } = useMarketplaceInstall()

async function loadConnection() {
    const res: any = await marketplaceApi.listConnections()
    const list = res?.data?.data ?? res?.data ?? []
    conn.value = Array.isArray(list) ? list[0] || null : null
}

async function loadCategories() {
    try {
        const res: any = await marketplaceApi.categories()
        categories.value = res?.data?.data ?? []
    } catch {
        categories.value = []
    }
}

async function loadApps() {
    loading.value = true
    try {
        const res: any = await marketplaceApi.apps({
            categoryId: selectedCategoryId.value || undefined,
            page: page.value,
            limit: pageSize.value
        })
        const payload = res?.data ?? {}
        rows.value = payload?.data ?? []
        isPublic.value = !!payload?.is_public
        total.value = payload?.pagination?.total ?? rows.value.length
    } finally {
        loading.value = false
    }
}

async function selectCategory(id: number) {
    selectedCategoryId.value = id
    page.value = 1 // 切分类回到第一页
    await loadApps()
}

async function onPageChange(p: number) {
    page.value = p
    await loadApps()
}

async function refresh() {
    await loadConnection()
    await Promise.all([loadCategories(), loadApps()])
}

// ========= 安装意图编排 =========

function openConnect(appCode: string | null, appName = '') {
    // 纯连接账号（无具体应用）时清掉上次选中的行，避免弹窗显示错误的应用名/图标
    if (!appCode) selectedRow.value = null
    connectAppCode.value = appCode
    connectAppName.value = appName
    connectVis.value = true
}

// 卡片「安装」点击：未连接→连接账号对话框；已连接→直接向后端验证权益
async function handleInstall(row: MarketplaceAppRow) {
    if (busy.value) return
    selectedRow.value = row
    if (!conn.value) {
        openConnect(row.app_code, row.app_name)
        return
    }
    busy.value = true
    try {
        const result = await resolveDirect(row.app_code)
        showResolution(result)
    } catch (e: any) {
        if (!e?.__handled) ElMessage.error(e?.message || '验证权益失败')
    } finally {
        busy.value = false
    }
}

// 连接对话框授权完成后回调（已带 install_resolution）
function onConnected(result: InstallResolution) {
    showResolution(result)
}

function showResolution(result: InstallResolution) {
    switch (result.status) {
        case 'connected':
            ElMessage.success('已连接元点官方市场账号')
            refresh()
            break
        case 'ready':
        case 'purchase_required':
        case 'incompatible':
            resolution.value = result
            confirmVis.value = true
            // 首次连接后权益已同步，刷新列表
            if (!conn.value) refresh()
            break
        case 'error':
            ElMessage.error(result.message || '处理失败')
            break
    }
}

async function onConfirmInstall(entitlementCode: string) {
    if (!entitlementCode) return
    try {
        await marketplaceApi.install({ entitlement_code: entitlementCode })
        ElMessage.success('安装成功')
        await refresh()
        emit('installed')
    } catch (e: any) {
        if (!e?.__handled) ElMessage.error(e?.message || '安装失败')
    }
}

async function onSync() {
    syncing.value = true
    try {
        const res: any = await marketplaceApi.sync()
        const data = res?.data ?? res
        ElMessage.success(`同步完成: ${data.changed_count} 个权益, 移除 ${data.removed_count}`)
        await loadApps()
    } catch (e: any) {
        if (!e?.__handled) ElMessage.error(e?.message || '同步失败')
    } finally {
        syncing.value = false
    }
}

async function onChangeAccount() {
    if (!conn.value) return
    try {
        await ElMessageBox.confirm(
            '更换账号会先解除当前连接，再重新连接新的元点官方市场账号。是否继续？',
            '更换账号',
            { type: 'warning', confirmButtonText: '继续', cancelButtonText: '取消' }
        )
        await marketplaceApi.revokeConnection(conn.value.id)
        conn.value = null
        rows.value = []
        openConnect(null)
    } catch (e: any) {
        if (e === 'cancel' || e?.action === 'cancel') return
        if (!e?.__handled && e?.message) ElMessage.error(e.message)
    }
}

async function onRevoke() {
    if (!conn.value) return
    try {
        await ElMessageBox.confirm('确认解除与元点官方市场账号的连接？', '解除连接', {
            type: 'warning'
        })
        await marketplaceApi.revokeConnection(conn.value.id)
        conn.value = null
        rows.value = []
        await refresh()
    } catch (e: any) {
        if (e === 'cancel' || e?.action === 'cancel') return
        if (!e?.__handled && e?.message) ElMessage.error(e.message)
    }
}

// v2.9.0: 手动 token 轮换
async function onManualRotate() {
    if (!conn.value) return
    try {
        await ElMessageBox.confirm(
            'Site 凭证将立即轮换（旧 token 失效）。若 Site 暂时不可达可能失败。',
            '手动轮换 token',
            { type: 'warning' }
        )
        await marketplaceApi.rotateConnectionToken(conn.value.id)
        ElMessage.success('token 轮换成功')
        await loadConnection()
    } catch (e: any) {
        if (e === 'cancel' || e?.action === 'cancel') return
        if (!e?.__handled && e?.message) ElMessage.error(e.message)
    }
}

onMounted(refresh)
</script>

<template>
    <div class="mkt-panel">
        <ElAlert v-if="!conn" type="info" :closable="false" class="banner-alert">
            <template #default>
                <span>
                    尚未连接元点官方市场账号，下方为公开应用目录。
                    <ElButton link type="primary" @click="openConnect(null)">连接账号</ElButton>
                    后可验证已购权益并一键安装。
                </span>
            </template>
        </ElAlert>
        <div v-else class="banner">
            <span>
                已连接 <b>{{ conn.bound_user_email || conn.token_prefix }}</b> · 上次心跳
                {{ conn.last_heartbeat_at || '—' }}
            </span>
            <span class="spacer" />
            <ElButton :loading="syncing" @click="onSync">立即同步</ElButton>
            <ElButton @click="onChangeAccount">更换账号</ElButton>
            <ElButton type="danger" @click="onRevoke">解除连接</ElButton>
        </div>

        <ElAlert
            v-if="conn?.token_rotate_overdue"
            type="error"
            :closable="false"
            show-icon
            class="banner-alert"
        >
            <template #default>
                <span>
                    Site 凭证轮换失败超过 90 天，自动重试持续失败。
                    <ElButton link type="primary" @click="onManualRotate">手动重试轮换</ElButton>
                </span>
            </template>
        </ElAlert>
        <ElAlert
            v-else-if="conn?.token_rotate_warn"
            type="warning"
            :closable="false"
            show-icon
            class="banner-alert"
        >
            <template #default>
                <span> Site 凭证即将自动轮换 (token 已使用 {{ conn.token_age_days }} 天) </span>
            </template>
        </ElAlert>

        <div v-if="categories.length > 0" class="category-chips">
            <ElButton
                :type="selectedCategoryId === 0 ? 'primary' : 'default'"
                :plain="selectedCategoryId !== 0"
                size="small"
                round
                @click="selectCategory(0)"
                >全部</ElButton
            >
            <ElButton
                v-for="c in categories"
                :key="c.id"
                :type="selectedCategoryId === c.id ? 'primary' : 'default'"
                :plain="selectedCategoryId !== c.id"
                size="small"
                round
                @click="selectCategory(c.id)"
                >{{ c.name }}</ElButton
            >
        </div>

        <div class="grid">
            <MarketplaceAppCard
                v-for="r in rows"
                :key="r.entitlement_code || r.app_code"
                :row="r"
                @changed="refresh"
                @install="handleInstall"
            />
            <ElEmpty
                v-if="!loading && rows.length === 0"
                :description="
                    isPublic ? '官方市场暂无上架应用' : '还没有已购应用，去元点官方市场购买一个吧'
                "
                class="empty-state"
            />
        </div>

        <div v-if="total > pageSize" class="pager">
            <ElPagination
                layout="prev, pager, next, total"
                background
                :current-page="page"
                :page-size="pageSize"
                :total="total"
                :disabled="loading"
                @current-change="onPageChange"
            />
        </div>

        <MarketplaceConnectDialog
            v-model:visible="connectVis"
            :app-code="connectAppCode"
            :app-name="connectAppName"
            @resolved="onConnected"
        />
        <InstallConfirmDialog
            v-model:visible="confirmVis"
            :resolution="resolution"
            :app-row="selectedRow"
            @confirm="onConfirmInstall"
        />
    </div>
</template>

<style scoped>
.mkt-panel {
    padding: 10px;
}

.banner {
    display: flex;
    align-items: center;
    padding: 10px;
    background: #f5f7fa;
    border-radius: 4px;
    margin-bottom: 12px;
}

.spacer {
    flex: 1;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.banner-alert {
    margin-bottom: 12px;
}

.category-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
    padding: 8px 0;
}

.empty-state {
    grid-column: 1 / -1;
    padding: 32px 0;
}

.pager {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
</style>
