<script setup lang="ts">
import '@/utils/echart'

import { ElMessage } from 'element-plus'
import QRCode from 'qrcode'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import VChart from 'vue-echarts'
import { useRouter } from 'vue-router'

import {
    getAccessInfo,
    getActiveRanking,
    getDashboardStats,
    getRecentActivities,
    type StoreAccessInfo
} from '@/api/dashboard'
import { pluginApi } from '@/api/plugin'
import { subscriptionApi } from '@/api/subscription'
import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'
import useUserStore from '@/store/modules/user.store'
import type {
    ActivityItem,
    DashboardStats,
    PlanInfo,
    RankingItem,
    SubscriptionInfo
} from '@/types/api'

import { formatBytes, subscriptionView } from './home-utils'

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()
const settingStore = useSettingStore()
const userStore = useUserStore()

// ── Dark mode ──────────────────────────────────────────────────────────────
const isDark = computed(() => {
    if (settingStore.themeMode === 'dark') return true
    if (settingStore.themeMode === 'light') return false
    return document.documentElement.classList.contains('dark')
})

const chartColors = computed(() =>
    isDark.value
        ? {
              surface: '#1a1b1e',
              tooltipBg: 'rgba(30,31,34,0.96)',
              tooltipBorder: '#2e2f33',
              tooltipText: '#e5e7eb',
              axisLine: '#2e2f33',
              splitLine: '#252629',
              axisLabel: '#6b7280'
          }
        : {
              surface: '#fff',
              tooltipBg: 'rgba(255,255,255,0.96)',
              tooltipBorder: '#eee',
              tooltipText: '#333',
              axisLine: '#e5e7eb',
              splitLine: '#f0f0f0',
              axisLabel: '#94a3b8'
          }
)

// ── Dashboard stats (REAL) ─────────────────────────────────────────────────
const stats = ref<DashboardStats | null>(null)
/** 按 days 缓存完整 stats，趋势切换复用，避免重复全量请求 */
const statsCache = new Map<number, DashboardStats>()

type LoadState = 'idle' | 'loading' | 'success' | 'error'
const overviewState = ref<LoadState>('idle')
const activitiesState = ref<LoadState>('idle')
const rankingState = ref<LoadState>('idle')
const subscriptionState = ref<LoadState>('idle')
const pluginsState = ref<LoadState>('idle')
const registerTrendState = ref<LoadState>('idle')
const activeTrendState = ref<LoadState>('idle')

function isAbortError(err: unknown): boolean {
    return (
        (err as { code?: string; name?: string })?.code === 'ERR_CANCELED' ||
        (err as { name?: string })?.name === 'CanceledError' ||
        (err as { name?: string })?.name === 'AbortError'
    )
}

// 两张趋势图相互独立：各自的天数与数据
type TrendPoint = { date: string; count: number }
const registerDays = ref(7)
const activeDays = ref(7)
const registerTrend = ref<TrendPoint[]>([])
const activeTrend = ref<TrendPoint[]>([])

let registerTrendAbort: AbortController | null = null
let activeTrendAbort: AbortController | null = null
let rankingAbort: AbortController | null = null

async function fetchStats(days: number, signal?: AbortSignal): Promise<DashboardStats | null> {
    const cached = statsCache.get(days)
    if (cached) return cached
    const res = await getDashboardStats(days, { signal })
    const data = res.data
    if (data) statsCache.set(days, data)
    return data ?? null
}

// KPI 概览固定按 7 日加载，并用同一响应为两张图播种初始数据
const loadStats = async () => {
    overviewState.value = 'loading'
    registerTrendState.value = 'loading'
    activeTrendState.value = 'loading'
    try {
        const data = await fetchStats(7)
        stats.value = data
        registerTrend.value = data?.registerTrend ?? []
        activeTrend.value = data?.loginTrend ?? []
        overviewState.value = data ? 'success' : 'error'
        registerTrendState.value = overviewState.value
        activeTrendState.value = overviewState.value
    } catch (err) {
        if (isAbortError(err)) return
        overviewState.value = 'error'
        registerTrendState.value = 'error'
        activeTrendState.value = 'error'
    }
}

const loadRegisterTrend = async () => {
    registerTrendAbort?.abort()
    const ac = new AbortController()
    registerTrendAbort = ac
    const days = registerDays.value
    const cached = statsCache.get(days)
    if (cached) {
        registerTrend.value = cached.registerTrend ?? []
        registerTrendState.value = 'success'
        return
    }
    registerTrendState.value = 'loading'
    try {
        const data = await fetchStats(days, ac.signal)
        if (ac.signal.aborted) return
        registerTrend.value = data?.registerTrend ?? []
        registerTrendState.value = data ? 'success' : 'error'
    } catch (err) {
        if (isAbortError(err) || ac.signal.aborted) return
        registerTrendState.value = 'error'
    }
}

const loadActiveTrend = async () => {
    activeTrendAbort?.abort()
    const ac = new AbortController()
    activeTrendAbort = ac
    const days = activeDays.value
    const cached = statsCache.get(days)
    if (cached) {
        activeTrend.value = cached.loginTrend ?? []
        activeTrendState.value = 'success'
        return
    }
    activeTrendState.value = 'loading'
    try {
        const data = await fetchStats(days, ac.signal)
        if (ac.signal.aborted) return
        activeTrend.value = data?.loginTrend ?? []
        activeTrendState.value = data ? 'success' : 'error'
    } catch (err) {
        if (isAbortError(err) || ac.signal.aborted) return
        activeTrendState.value = 'error'
    }
}

const switchRegisterDays = (days: number) => {
    if (registerDays.value === days) return
    registerDays.value = days
    loadRegisterTrend()
}

const switchActiveDays = (days: number) => {
    if (activeDays.value === days) return
    activeDays.value = days
    loadActiveTrend()
}

// ── 已开通应用数量 (REAL) ───────────────────────────────────────────────────
const pluginCount = ref(0)
const pluginsLoaded = ref(false)

const loadPluginCount = async () => {
    pluginsState.value = 'loading'
    try {
        const res = await pluginApi.list()
        pluginCount.value = (res.data ?? []).filter((p) => p.tenant_status === 1).length
        pluginsLoaded.value = true
        pluginsState.value = 'success'
    } catch (err) {
        if (isAbortError(err)) return
        pluginsState.value = 'error'
    }
}

// ── 最近动态 (REAL) ─────────────────────────────────────────────────────────
const activities = ref<ActivityItem[]>([])

const loadActivities = async () => {
    activitiesState.value = 'loading'
    try {
        const res = await getRecentActivities()
        activities.value = res.data ?? []
        activitiesState.value = 'success'
    } catch (err) {
        if (isAbortError(err)) return
        activitiesState.value = 'error'
    }
}

// ── 用户活跃排行 (REAL) ─────────────────────────────────────────────────────
const rankingPeriod = ref<'day' | 'week' | 'month'>('day')
const ranking = ref<RankingItem[]>([])
const periodOptions: Array<{ key: 'day' | 'week' | 'month'; label: string }> = [
    { key: 'day', label: '今日' },
    { key: 'week', label: '本周' },
    { key: 'month', label: '本月' }
]

const loadRanking = async () => {
    rankingAbort?.abort()
    const ac = new AbortController()
    rankingAbort = ac
    rankingState.value = 'loading'
    try {
        const res = await getActiveRanking(rankingPeriod.value, { signal: ac.signal })
        if (ac.signal.aborted) return
        ranking.value = res.data?.list ?? []
        rankingState.value = 'success'
    } catch (err) {
        if (isAbortError(err) || ac.signal.aborted) return
        rankingState.value = 'error'
    }
}

const switchRankingPeriod = (period: 'day' | 'week' | 'month') => {
    if (rankingPeriod.value === period) return
    rankingPeriod.value = period
    loadRanking()
}

// ── 存储用量 (REAL，源自 saas.limits) ───────────────────────────────────────
const storage = computed(() => {
    const limits = userStore.saas?.limits
    const used = limits?.storage_used_bytes ?? 0
    const total = limits?.storage_limit_bytes ?? 0
    const hasLimit = total > 0
    return {
        usedText: formatBytes(used),
        totalText: hasLimit ? formatBytes(total) : '不限',
        pct: hasLimit ? Math.min(100, Math.round((used / total) * 100)) : 0,
        hasLimit
    }
})

const adminCount = computed(() => (stats.value ? stats.value.adminCount : '—'))

// ── Subscription (REAL) ────────────────────────────────────────────────────
const subInfo = ref<SubscriptionInfo | null>(null)
const plans = ref<PlanInfo[]>([])

const subView = computed(() => subscriptionView(subInfo.value, plans.value))

const loadSubscription = async () => {
    subscriptionState.value = 'loading'
    try {
        const [subRes, plansRes] = await Promise.all([
            subscriptionApi.current(),
            subscriptionApi.plans()
        ])
        subInfo.value = subRes.data?.subscription ?? null
        plans.value = plansRes.data?.list ?? []
        subscriptionState.value = 'success'
    } catch (err) {
        if (isAbortError(err)) return
        subscriptionState.value = 'error'
    }
}

const retryOverview = () => {
    statsCache.delete(7)
    loadStats()
}

const retryAllFailed = () => {
    if (overviewState.value === 'error') retryOverview()
    if (subscriptionState.value === 'error') loadSubscription()
    if (pluginsState.value === 'error') loadPluginCount()
    if (activitiesState.value === 'error') loadActivities()
    if (rankingState.value === 'error') loadRanking()
    if (registerTrendState.value === 'error') loadRegisterTrend()
    if (activeTrendState.value === 'error') loadActiveTrend()
}

onMounted(() => {
    loadStats()
    loadSubscription()
    loadPluginCount()
    loadActivities()
    loadRanking()
})

// ── Trend chart option builder (reused from prior impl) ────────────────────
// ECharts 把颜色交给 canvas 解析，canvas 无法识别 CSS 变量（如 var(--el-color-primary)），
// 必须先解析成真实色值；同理渐变色阶用 rgba 拼接，避免对 hex 位数做假设。
const cssColor = (name: string, fallback: string) => {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim()
    return v || fallback
}

const hexToRgba = (hex: string, alpha: number) => {
    const h = hex.replace('#', '')
    const full =
        h.length === 3
            ? h
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : h
    const r = parseInt(full.slice(0, 2), 16)
    const g = parseInt(full.slice(2, 4), 16)
    const b = parseInt(full.slice(4, 6), 16)
    return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const buildTrendOption = (data: Array<{ date: string; count: number }>, color: string) => ({
    tooltip: {
        trigger: 'axis',
        backgroundColor: chartColors.value.tooltipBg,
        borderColor: chartColors.value.tooltipBorder,
        textStyle: { color: chartColors.value.tooltipText, fontSize: 12 }
    },
    grid: { top: 12, right: 16, bottom: 28, left: 44 },
    xAxis: {
        type: 'category',
        data: data.map((i) => i.date),
        axisLine: { lineStyle: { color: chartColors.value.axisLine } },
        axisLabel: { color: chartColors.value.axisLabel, fontSize: 11 },
        axisTick: { show: false }
    },
    yAxis: {
        type: 'value',
        minInterval: 1,
        splitLine: { lineStyle: { type: 'dashed', color: chartColors.value.splitLine } },
        axisLabel: { color: chartColors.value.axisLabel, fontSize: 11 }
    },
    series: [
        {
            type: 'line',
            data: data.map((i) => i.count),
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            lineStyle: { color, width: 2 },
            itemStyle: { color, borderWidth: 2, borderColor: '#fff' },
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [
                        { offset: 0, color: hexToRgba(color, 0.25) },
                        { offset: 1, color: hexToRgba(color, 0.02) }
                    ]
                }
            }
        }
    ]
})

// 始终构建 option（即使数据为空/全 0 也展示坐标轴与零基线，而非空白）
const registerTrendOption = computed(() =>
    buildTrendOption(registerTrend.value, cssColor('--el-color-primary', '#2c73ff'))
)

const loginTrendOption = computed(() => buildTrendOption(activeTrend.value, '#36CFC9'))

// ── Helpers ────────────────────────────────────────────────────────────────
const formatDate = (iso: string) => {
    if (!iso) return '—'
    return iso.replace('T', ' ').slice(0, 19)
}

const goUpgrade = () => router.push('/subscription')

// ── 店铺访问 ───────────────────────────────────────────────────────────────
const accessDialogVisible = ref(false)
const accessLoading = ref(false)
const accessTab = ref('h5')
const accessInfo = ref<StoreAccessInfo | null>(null)
const h5QrCanvas = ref<HTMLCanvasElement | null>(null)
const pcQrCanvas = ref<HTMLCanvasElement | null>(null)

const h5ReasonText = computed(() => {
    const code = accessInfo.value?.h5.reason_code
    if (code === 'not_released') return t('dashboard.storeAccess.h5NotReleased')
    if (code === 'no_domain') return t('dashboard.storeAccess.h5NoDomain')
    return t('dashboard.storeAccess.h5NotBuilt')
})

const pcReasonText = computed(() => {
    const code = accessInfo.value?.pc.reason_code
    if (code === 'not_configured') return t('dashboard.storeAccess.pcNotConfigured')
    if (code === 'disabled') return t('dashboard.storeAccess.pcDisabled')
    return t('dashboard.storeAccess.pcNoDomain')
})

const miniQrSrc = computed(() => {
    const raw = accessInfo.value?.miniprogram.qr_url || ''
    return raw ? appStore.getImageUrl(raw) : ''
})

async function openAccessDialog() {
    accessDialogVisible.value = true
    accessTab.value = 'h5'
    accessLoading.value = true
    try {
        const res = await getAccessInfo()
        accessInfo.value = res.data
    } catch {
        accessInfo.value = null
    } finally {
        accessLoading.value = false
        await nextTick()
        await renderPortalQr('h5')
    }
}

async function renderPortalQr(channel: 'h5' | 'pc') {
    const info = accessInfo.value?.[channel]
    const canvas = channel === 'h5' ? h5QrCanvas.value : pcQrCanvas.value
    const url = info?.url
    if (!info?.ready || !url || !canvas) return
    try {
        await QRCode.toCanvas(canvas, url, { width: 180, margin: 1 })
    } catch {
        // ignore qr render errors
    }
}

watch(accessTab, async (tab) => {
    if (tab === 'h5' || tab === 'pc') {
        await nextTick()
        await renderPortalQr(tab)
    }
})

async function copyPortalLink(channel: 'h5' | 'pc') {
    const url = accessInfo.value?.[channel].url
    if (!url) return
    try {
        await navigator.clipboard.writeText(url)
        ElMessage.success(t('dashboard.storeAccess.copied'))
    } catch {
        ElMessage.error(url)
    }
}

function openPortalLink(channel: 'h5' | 'pc') {
    const url = accessInfo.value?.[channel].url
    if (url) window.open(url, '_blank')
}

function goAccessAction(path?: string) {
    if (!path) return
    accessDialogVisible.value = false
    router.push(path)
}

// ── KPI tiles (data overview) — 全部真实数据 ────────────────────────────────
const kpiTiles = computed(() => {
    const s = stats.value
    const n = (v: number) => v.toLocaleString()
    return [
        { label: '活跃用户', value: s ? n(s.activeUsers) : '—', trend: s?.trends.activeUsers },
        { label: '新增用户', value: s ? n(s.todayNewUsers) : '—', trend: s?.trends.todayNewUsers },
        { label: '总用户数', value: s ? n(s.totalUsers) : '—', trend: s?.trends.totalUsers },
        {
            label: '今日登录',
            value: s ? n(s.todayLoginCount) : '—',
            trend: s?.trends.todayLoginCount
        },
        { label: '管理员数', value: s ? n(s.adminCount) : '—' },
        {
            label: '已开通应用',
            value:
                pluginsState.value === 'error'
                    ? '—'
                    : pluginsLoaded.value
                      ? pluginCount.value.toLocaleString()
                      : '—'
        }
    ]
})

// 动态条目左侧色点：成功登录=绿，失败=红，操作=蓝
const activityDotClass = (type: ActivityItem['type']) =>
    type === 'login_success' ? 'ok' : type === 'login_failed' ? 'fail' : 'op'

</script>

<template>
    <div class="home">
        <div
            v-if="
                overviewState === 'error' ||
                activitiesState === 'error' ||
                rankingState === 'error' ||
                subscriptionState === 'error' ||
                pluginsState === 'error'
            "
            class="load-error-banner"
        >
            <span>部分数据加载失败</span>
            <button type="button" class="btn-retry" @click="retryAllFailed">重试</button>
        </div>
        <div class="home-grid">
            <!-- ── Left column ── -->
            <div class="home-main">
                <!-- 数据概览 (6 KPI，全部真实数据) -->
                <section class="card">
                    <div class="card-header-row">
                        <h3 class="card-title">数据概览</h3>
                        <button
                            v-if="overviewState === 'error'"
                            type="button"
                            class="btn-retry-link"
                            @click="retryOverview"
                        >
                            重试
                        </button>
                    </div>
                    <div v-if="overviewState === 'error'" class="empty-hint error-hint">
                        统计数据加载失败
                    </div>
                    <div v-else class="kpi-grid">
                        <div
                            v-for="(tile, i) in kpiTiles"
                            :key="i"
                            class="kpi-tile"
                            :class="{ hot: i === 0 }"
                        >
                            <div class="kpi-label">{{ tile.label }}</div>
                            <div class="kpi-value">
                                {{ tile.value }}
                                <span
                                    v-if="tile.trend && tile.trend.value > 0"
                                    class="kpi-trend"
                                    :class="tile.trend.type"
                                >
                                    {{ tile.trend.type === 'up' ? '▲' : '▼' }}{{ tile.trend.value
                                    }}{{ tile.trend.unit === 'percent' ? '%' : '' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 双趋势图: registerTrend / loginTrend (REAL) -->
                <div class="trend-2col">
                    <section class="card">
                        <div class="card-header-row">
                            <h3 class="card-title">新增用户趋势</h3>
                            <div class="period-tabs">
                                <span
                                    v-for="d in [7, 30]"
                                    :key="d"
                                    class="period-tab"
                                    :class="{ active: registerDays === d }"
                                    @click="switchRegisterDays(d)"
                                    >近{{ d }}日</span
                                >
                            </div>
                        </div>
                        <div v-if="registerTrendState === 'error'" class="empty-hint error-hint">
                            加载失败
                            <button type="button" class="btn-retry-link" @click="loadRegisterTrend">
                                重试
                            </button>
                        </div>
                        <v-chart
                            v-else
                            class="trend-chart"
                            :option="registerTrendOption"
                            autoresize
                        />
                    </section>
                    <section class="card">
                        <div class="card-header-row">
                            <h3 class="card-title">活跃用户趋势</h3>
                            <div class="period-tabs">
                                <span
                                    v-for="d in [7, 30]"
                                    :key="d"
                                    class="period-tab"
                                    :class="{ active: activeDays === d }"
                                    @click="switchActiveDays(d)"
                                    >近{{ d }}日</span
                                >
                            </div>
                        </div>
                        <div v-if="activeTrendState === 'error'" class="empty-hint error-hint">
                            加载失败
                            <button type="button" class="btn-retry-link" @click="loadActiveTrend">
                                重试
                            </button>
                        </div>
                        <v-chart v-else class="trend-chart" :option="loginTrendOption" autoresize />
                    </section>
                </div>

                <!-- 最近动态 (REAL) -->
                <section class="card">
                    <div class="card-header-row">
                        <h3 class="card-title">最近动态</h3>
                        <button
                            v-if="activitiesState === 'error'"
                            type="button"
                            class="btn-retry-link"
                            @click="loadActivities"
                        >
                            重试
                        </button>
                    </div>
                    <div v-if="activitiesState === 'error'" class="empty-hint error-hint">
                        动态加载失败
                    </div>
                    <div v-else-if="activities.length" class="activity-list">
                        <div v-for="(a, i) in activities" :key="i" class="activity-item">
                            <span class="activity-dot" :class="activityDotClass(a.type)" />
                            <div class="activity-body">
                                <div class="activity-text">
                                    <span class="activity-user">{{ a.username }}</span>
                                    {{ a.description }}
                                </div>
                                <div class="activity-time">{{ a.relative_time }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-hint">
                        {{ activitiesState === 'loading' ? '加载中…' : '暂无动态' }}
                    </div>
                </section>

                <!-- 用户活跃排行 (REAL) -->
                <section class="card">
                    <div class="card-header-row">
                        <h3 class="card-title">用户活跃排行</h3>
                        <div class="period-tabs">
                            <span
                                v-for="opt in periodOptions"
                                :key="opt.key"
                                class="period-tab"
                                :class="{ active: rankingPeriod === opt.key }"
                                @click="switchRankingPeriod(opt.key)"
                                >{{ opt.label }}</span
                            >
                        </div>
                    </div>
                    <div v-if="rankingState === 'error'" class="empty-hint error-hint">
                        排行加载失败
                        <button type="button" class="btn-retry-link" @click="loadRanking">
                            重试
                        </button>
                    </div>
                    <div v-else-if="ranking.length" class="ranking-list">
                        <div v-for="item in ranking" :key="item.rank" class="ranking-item">
                            <span class="ranking-no" :class="{ top: item.rank <= 3 }">{{
                                item.rank
                            }}</span>
                            <span class="ranking-user">{{ item.username }}</span>
                            <span class="ranking-count">{{ item.count }} 次</span>
                        </div>
                    </div>
                    <div v-else class="empty-hint">
                        {{ rankingState === 'loading' ? '加载中…' : '暂无数据' }}
                    </div>
                </section>
            </div>

            <!-- ── Right sidebar ── -->
            <aside class="home-side">
                <!-- 订阅卡 (REAL) -->
                <section class="card sub-card">
                    <div class="sub-glow" />
                    <template v-if="subscriptionState === 'error'">
                        <div class="empty-hint error-hint">
                            订阅信息加载失败
                            <button type="button" class="btn-retry-link" @click="loadSubscription">
                                重试
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="sub-header">
                            <div class="sub-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="#fff">
                                    <path d="M5 12c0-3 2-5 5-5s5 2 5 5-2 5-5 5-5-2-5-5Z" />
                                    <path d="M14 6l4-2v16l-4-2" />
                                </svg>
                            </div>
                            <div>
                                <div class="sub-name">{{ subView.planName }}</div>
                                <div class="sub-hint">
                                    {{ subView.isMock ? '暂无订阅信息' : '当前套餐剩余' }}
                                </div>
                            </div>
                        </div>
                        <div class="sub-rows">
                            <div class="sub-row">
                                <span class="sub-row-label">套餐版本</span>
                                <span class="sub-row-val">{{ subView.planName }}</span>
                            </div>
                            <div class="sub-row">
                                <span class="sub-row-label">剩余天数</span>
                                <span class="sub-row-days">{{
                                    subView.isMock ? '—' : subView.daysLeft + ' 天'
                                }}</span>
                            </div>
                            <div class="sub-row">
                                <span class="sub-row-label">到期时间</span>
                                <span class="sub-row-val dim">{{ formatDate(subView.endsAt) }}</span>
                            </div>
                        </div>
                        <button class="btn-upgrade" @click="goUpgrade">立即升级</button>
                    </template>
                </section>

                <!-- 店铺访问 -->
                <section class="card access-card">
                    <h3 class="card-title">{{ $t('dashboard.storeAccess.title') }}</h3>
                    <p class="access-hint">{{ $t('dashboard.storeAccess.hint') }}</p>
                    <button class="btn-access" type="button" @click="openAccessDialog">
                        {{ $t('dashboard.storeAccess.visitNow') }}
                    </button>
                </section>

                <!-- 资源用量 (REAL) -->
                <section class="card">
                    <h3 class="card-title">资源用量</h3>
                    <div class="resource-list">
                        <!-- 存储空间：真实，来自 saas.limits -->
                        <div class="resource-item">
                            <div class="resource-meta">
                                <span class="resource-label">存储空间</span>
                                <span class="resource-usage"
                                    >{{ storage.usedText }} / {{ storage.totalText }}</span
                                >
                            </div>
                            <div v-if="storage.hasLimit" class="resource-bar-bg">
                                <div
                                    class="resource-bar-fill"
                                    :class="{ warn: storage.pct > 80 }"
                                    :style="{ width: storage.pct + '%' }"
                                />
                            </div>
                        </div>
                        <!-- 子账号：真实，管理员数量 -->
                        <div class="resource-item">
                            <div class="resource-meta">
                                <span class="resource-label">子账号</span>
                                <span class="resource-usage">{{ adminCount }} 个</span>
                            </div>
                        </div>
                    </div>
                </section>

            </aside>
        </div>

        <el-dialog
            v-model="accessDialogVisible"
            :title="$t('dashboard.storeAccess.dialogTitle')"
            class="dlg-md"
            append-to-body
            destroy-on-close
        >
            <div v-loading="accessLoading" class="access-dialog">
                <el-tabs v-model="accessTab">
                    <el-tab-pane :label="$t('dashboard.storeAccess.tabH5')" name="h5">
                        <template v-if="accessInfo?.h5.ready">
                            <div class="access-ready">
                                <canvas ref="h5QrCanvas" class="access-qr" />
                                <div class="access-url mono">{{ accessInfo.h5.url }}</div>
                                <div class="access-actions">
                                    <el-button type="primary" @click="copyPortalLink('h5')">
                                        {{ $t('dashboard.storeAccess.copyLink') }}
                                    </el-button>
                                    <el-button @click="openPortalLink('h5')">
                                        {{ $t('dashboard.storeAccess.openLink') }}
                                    </el-button>
                                </div>
                            </div>
                        </template>
                        <div v-else class="access-empty">
                            <p>{{ h5ReasonText }}</p>
                            <el-button
                                type="primary"
                                @click="goAccessAction(accessInfo?.h5.action_path)"
                            >
                                {{ $t('dashboard.storeAccess.goBuild') }}
                            </el-button>
                        </div>
                    </el-tab-pane>

                    <el-tab-pane :label="$t('dashboard.storeAccess.tabMini')" name="miniprogram">
                        <template v-if="accessInfo?.miniprogram.ready">
                            <div class="access-ready">
                                <img :src="miniQrSrc" alt="mini-qrcode" class="access-qr-img" />
                            </div>
                        </template>
                        <div v-else class="access-empty">
                            <p>{{ $t('dashboard.storeAccess.miniNoQrcode') }}</p>
                            <el-button
                                type="primary"
                                @click="goAccessAction(accessInfo?.miniprogram.action_path)"
                            >
                                {{ $t('dashboard.storeAccess.goMiniConfig') }}
                            </el-button>
                        </div>
                    </el-tab-pane>

                    <el-tab-pane :label="$t('dashboard.storeAccess.tabPc')" name="pc">
                        <template v-if="accessInfo?.pc.ready">
                            <div class="access-ready">
                                <canvas ref="pcQrCanvas" class="access-qr" />
                                <div class="access-url mono">{{ accessInfo.pc.url }}</div>
                                <div class="access-actions">
                                    <el-button type="primary" @click="copyPortalLink('pc')">
                                        {{ $t('dashboard.storeAccess.copyLink') }}
                                    </el-button>
                                    <el-button @click="openPortalLink('pc')">
                                        {{ $t('dashboard.storeAccess.openLink') }}
                                    </el-button>
                                </div>
                            </div>
                        </template>
                        <div v-else class="access-empty">
                            <p>{{ pcReasonText }}</p>
                            <el-button
                                v-if="accessInfo?.pc.reason_code !== 'no_domain'"
                                type="primary"
                                @click="goAccessAction(accessInfo?.pc.action_path)"
                            >
                                {{ $t('dashboard.storeAccess.goPcConfig') }}
                            </el-button>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped lang="scss">
/* ── Layout ── */
.home {
    padding: 0;
    min-height: 100%;
}

.load-error-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    padding: 10px 14px;
    border-radius: 4px;
    background: var(--el-color-danger-light-9, #fef0f0);
    color: var(--el-color-danger, #f56c6c);
    font-size: 13px;
}

.btn-retry {
    border: 1px solid currentColor;
    border-radius: 4px;
    background: transparent;
    color: inherit;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    cursor: pointer;
}

.btn-retry-link {
    border: none;
    background: none;
    color: var(--el-color-primary);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}

.error-hint {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.home-grid {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 14px;
    align-items: start;
}

.home-main {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-width: 0;
}

.home-side {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* ── Card base ── */
.card {
    background: var(--color-surface);
    border: none;
    border-radius: 4px;
    box-shadow: var(--shadow-sm);
    padding: 16px 18px;
}

/* ── Card title：竖条由全局 .card-title（crud-layout.scss，::before）提供，本地只收紧尺寸 ── */
.card-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    margin: 0 0 14px;
}

.card-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;

    .card-title {
        margin: 0;
    }
}

/* ── Period tabs ── */
.period-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 10px;
}

.period-tab {
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 10px;
    background: var(--gray-100);
    color: var(--ink-3);
    cursor: pointer;
    transition: all var(--motion-duration-fast);
    font-weight: 500;

    &.active {
        background: var(--el-color-primary);
        color: #fff;
    }
}

/* ── KPI tiles (数据概览) ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
}

.kpi-tile {
    border: none;
    border-radius: 4px;
    padding: 12px 14px;
    background: var(--gray-50);

    &.hot {
        background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
    }
}

html.dark .kpi-tile {
    background: var(--gray-100);

    &.hot {
        background: var(--gray-100);
    }
}

.kpi-label {
    font-size: 11.5px;
    color: var(--ink-3);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 2px;
}

.kpi-value {
    font-size: 24px;
    font-weight: 600;
    color: var(--ink);
    margin-top: 6px;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.kpi-trend {
    font-size: 11px;
    font-weight: 600;

    &.up {
        color: var(--color-success-plain, #16a34a);
    }

    &.down {
        color: var(--color-danger-plain, #ef4444);
    }
}

/* ── Dual trend charts ── */
.trend-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;

    .card {
        display: flex;
        flex-direction: column;
    }
}

.trend-chart {
    width: 100%;
    /* 显式高度：不能用 flex:1（其 flex-basis 解析为 0% 会在自适应高度的列容器里塌成 0） */
    height: 200px;
}

/* ── Recent activities (REAL) ── */
.activity-list {
    display: flex;
    flex-direction: column;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 9px 2px;

    & + & {
        border-top: 1px solid var(--el-border-color-lighter, var(--color-border));
    }
}

.activity-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;

    &.ok {
        background: var(--color-success-plain, #16a34a);
    }

    &.fail {
        background: var(--color-danger-plain, #ef4444);
    }

    &.op {
        background: var(--el-color-primary);
    }
}

.activity-body {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.activity-text {
    font-size: 12.5px;
    color: var(--ink-2);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.activity-user {
    font-weight: 600;
    color: var(--ink);
}

.activity-time {
    font-size: 11px;
    color: var(--ink-3);
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}

/* ── Active ranking (REAL) ── */
.ranking-list {
    display: flex;
    flex-direction: column;
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 2px;

    & + & {
        border-top: 1px solid var(--el-border-color-lighter, var(--color-border));
    }
}

.ranking-no {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    background: var(--gray-100);
    color: var(--ink-3);
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &.top {
        background: var(--el-color-primary);
        color: #fff;
    }
}

.ranking-user {
    flex: 1;
    min-width: 0;
    font-size: 12.5px;
    color: var(--ink-2);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ranking-count {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
}

/* ── Empty hint ── */
.empty-hint {
    padding: 28px 0;
    text-align: center;
    font-size: 12.5px;
    color: var(--ink-3);
}

/* ── Subscription card (REAL) ── */
.sub-card {
    position: relative;
    overflow: hidden;
}

.sub-glow {
    position: absolute;
    right: -20px;
    top: -20px;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 224, 150, 0.5) 0%, rgba(255, 224, 150, 0) 70%);
    pointer-events: none;
}

.sub-header {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    z-index: 1;
}

.sub-icon {
    width: 38px;
    height: 38px;
    border-radius: 4px;
    background: linear-gradient(135deg, #ffe6b0, #ff9c5c);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sub-name {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--ink);
}

.sub-hint {
    font-size: 11px;
    color: var(--ink-3);
    margin-top: 2px;
}

.sub-rows {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 14px;
    font-size: 11.5px;
    color: var(--ink-3);
    position: relative;
    z-index: 1;
}

.sub-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sub-row-label {
    color: var(--ink-3);
}

.sub-row-val {
    color: var(--ink-2);
    font-weight: 500;

    &.dim {
        color: var(--ink-3);
        font-weight: 400;
        font-size: 11px;
    }
}

.sub-row-days {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
}

.btn-upgrade {
    margin-top: 14px;
    width: 100%;
    height: 34px;
    border: none;
    border-radius: 4px;
    background: linear-gradient(
        90deg,
        var(--el-color-primary-dark-2, #1b4fe8) 0%,
        var(--el-color-primary, #2c73ff) 100%
    );
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: opacity var(--motion-duration-fast);
    position: relative;
    z-index: 1;

    &:hover {
        opacity: 0.9;
    }
}

.access-hint {
    margin: 0 0 12px;
    font-size: 12px;
    color: var(--ink-3);
    line-height: 1.5;
}

.btn-access {
    width: 100%;
    height: 34px;
    border: 1px solid var(--el-color-primary);
    border-radius: 4px;
    background: transparent;
    color: var(--el-color-primary);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background var(--motion-duration-fast);

    &:hover {
        background: var(--el-color-primary-light-9);
    }
}

.access-dialog {
    min-height: 220px;
}

.access-ready {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 8px 0 4px;
}

.access-qr,
.access-qr-img {
    width: 180px;
    height: 180px;
    border-radius: 6px;
    border: 1px solid var(--el-border-color-lighter);
    object-fit: contain;
    background: #fff;
}

.access-url {
    max-width: 100%;
    font-size: 12px;
    color: var(--ink-2);
    word-break: break-all;
    text-align: center;
}

.access-actions {
    display: flex;
    gap: 8px;
}

.access-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 36px 12px;
    text-align: center;
    color: var(--ink-3);
    font-size: 13px;
}

/* ── Resource usage ── */
.resource-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.resource-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.resource-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.resource-label {
    color: var(--ink-2);
}

.resource-usage {
    color: var(--ink-3);
    font-variant-numeric: tabular-nums;
}

.resource-bar-bg {
    height: 6px;
    border-radius: 999px;
    background: var(--gray-200);
    overflow: hidden;
}

.resource-bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--el-color-primary), #2c73ff);
    transition: width var(--motion-duration-base);

    &.warn {
        background: linear-gradient(90deg, #ff9c5c, #ff6438);
    }
}

/* ── Responsive ── */
@media (max-width: 1100px) {
    .home-grid {
        grid-template-columns: 1fr;
    }

    .home-side {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 900px) {
    .kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 720px) {
    .trend-2col {
        grid-template-columns: 1fr;
    }

    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .home-side {
        display: flex;
        flex-direction: column;
    }

    .browse-layout {
        grid-template-columns: 1fr;
    }
}
</style>
