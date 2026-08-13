<template>
    <div class="platform-dashboard">
        <!-- 顶部统计卡片：3列 × 2行 -->
        <el-row :gutter="20">
            <el-col :span="8">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-label">{{ $t('dashboard.totalTenants') }}</div>
                    <div class="stat-value">{{ stats.tenant_total }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-label">{{ $t('dashboard.activeTenants') }}</div>
                    <div class="stat-value" style="color: #67c23a">{{ stats.tenant_active }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-label">{{ $t('dashboard.expiring7d') }}</div>
                    <div
                        class="stat-value"
                        :style="{ color: stats.expiring_7d > 0 ? '#e6a23c' : '#909399' }"
                    >
                        {{ stats.expiring_7d }}
                    </div>
                </el-card>
            </el-col>
        </el-row>
        <el-row :gutter="20" style="margin-top: 16px">
            <el-col :span="8">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-label">{{ $t('dashboard.monthlyRevenue') }}</div>
                    <div class="stat-value" style="color: #409eff">
                        {{ fmtMoney(stats.monthly_revenue) }}
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-label">{{ $t('dashboard.yearlyRevenue') }}</div>
                    <div class="stat-value" style="color: #409eff">
                        {{ fmtMoney(stats.yearly_revenue) }}
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-label">{{ $t('dashboard.adminTotal') }}</div>
                    <div class="stat-value">{{ stats.admin_total }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 中间图表区：收入趋势 + 套餐分布 -->
        <el-row :gutter="20" style="margin-top: 24px">
            <el-col :span="14">
                <el-card shadow="hover">
                    <template #header>
                        <span class="font-medium">{{ $t('dashboard.revenueTrend') }}</span>
                    </template>
                    <VChart class="chart-box" :option="revenueChartOption" autoresize />
                </el-card>
            </el-col>
            <el-col :span="10">
                <el-card shadow="hover">
                    <template #header>
                        <span class="font-medium">{{ $t('dashboard.planDistribution') }}</span>
                    </template>
                    <div
                        v-if="!stats.plan_distribution || stats.plan_distribution.length === 0"
                        class="chart-empty"
                    >
                        {{ $t('dashboard.noPlanData') }}
                    </div>
                    <VChart v-else class="chart-box" :option="planPieOption" autoresize />
                </el-card>
            </el-col>
        </el-row>

        <!-- 底部区域：租户趋势柱状图 + 存储 TOP10 表格 -->
        <el-row :gutter="20" style="margin-top: 24px">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span class="font-medium">{{ $t('dashboard.newTenants7d') }}</span>
                    </template>
                    <div v-if="maxTrend === 0" class="chart-empty">
                        {{ $t('dashboard.noNewTenants') }}
                    </div>
                    <VChart v-else class="chart-box" :option="tenantTrendOption" autoresize />
                </el-card>
            </el-col>

            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span class="font-medium">{{ $t('dashboard.storageTop10') }}</span>
                    </template>
                    <el-table :data="stats.storage_top10" size="small" stripe style="width: 100%">
                        <el-table-column
                            :label="$t('tenant.tenantName')"
                            prop="name"
                            min-width="120"
                            show-overflow-tooltip
                        />
                        <el-table-column :label="$t('dashboard.storageUsed')" min-width="90">
                            <template #default="{ row }">{{
                                fmtBytes(row.storage_used_bytes)
                            }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('dashboard.storageLimit')" min-width="90">
                            <template #default="{ row }">{{
                                fmtBytes(row.storage_limit_bytes)
                            }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('dashboard.storageRatio')" min-width="80">
                            <template #default="{ row }">
                                <span
                                    :style="{
                                        color: storageRatio(row) > 80 ? '#f56c6c' : '#909399'
                                    }"
                                >
                                    {{ storageRatio(row) }}%
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup lang="ts">
import { Refresh } from '@element-plus/icons-vue'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import {
    GridComponent,
    LegendComponent,
    TitleComponent,
    TooltipComponent
} from 'echarts/components'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, onMounted, reactive, ref } from 'vue'
import VChart from 'vue-echarts'

import { dashboardApi } from '@/api/dashboard'

use([
    CanvasRenderer,
    LineChart,
    PieChart,
    BarChart,
    TitleComponent,
    TooltipComponent,
    LegendComponent,
    GridComponent
])

// ─── State ───────────────────────────────────────────────────────────────────
const loading = ref(false)

const stats = reactive<{
    tenant_total: number
    tenant_active: number
    tenant_expiring: number
    expiring_7d: number
    expiring_30d: number
    plan_total: number
    plugin_total: number
    admin_total: number
    monthly_revenue: number
    last_month_revenue: number
    yearly_revenue: number
    plan_distribution: { name: string; count: number }[]
    storage_top10: {
        id: number
        name: string
        storage_used_bytes: number
        storage_limit_bytes: number
    }[]
    tenant_trend: { date: string; count: number }[]
}>({
    tenant_total: 0,
    tenant_active: 0,
    tenant_expiring: 0,
    expiring_7d: 0,
    expiring_30d: 0,
    plan_total: 0,
    plugin_total: 0,
    admin_total: 0,
    monthly_revenue: 0,
    last_month_revenue: 0,
    yearly_revenue: 0,
    plan_distribution: [],
    storage_top10: [],
    tenant_trend: []
})

const revenueTrendData = ref<{ month: string; amount: number }[]>([])

// ─── Helpers ─────────────────────────────────────────────────────────────────
/** amount 已是元（decimal 字段），直接格式化显示 */
function fmtMoney(val: number) {
    return (val ?? 0).toLocaleString('zh-CN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

function fmtBytes(bytes: number) {
    if (!bytes) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let i = 0
    let v = bytes
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024
        i++
    }
    return `${v.toFixed(1)} ${units[i]}`
}

function storageRatio(row: { storage_used_bytes: number; storage_limit_bytes: number }) {
    if (!row.storage_limit_bytes) return 0
    return Math.min(Math.round((row.storage_used_bytes / row.storage_limit_bytes) * 100), 100)
}

// ─── Computed chart options ───────────────────────────────────────────────────
const maxTrend = computed(() =>
    stats.tenant_trend.reduce((max, cur) => Math.max(max, cur.count), 0)
)

const revenueChartOption = computed(() => ({
    tooltip: {
        trigger: 'axis',
        formatter: (p: any) => `${p[0].name}<br/>¥ ${fmtMoney(p[0].value)}`
    },
    grid: { left: 60, right: 20, top: 20, bottom: 40 },
    xAxis: {
        type: 'category',
        data: revenueTrendData.value.map((d) => d.month),
        axisLabel: { rotate: 30, fontSize: 11 }
    },
    yAxis: {
        type: 'value',
        axisLabel: { formatter: (v: number) => `¥${v}` }
    },
    series: [
        {
            type: 'line',
            data: revenueTrendData.value.map((d) => d.amount),
            smooth: true,
            areaStyle: { opacity: 0.15 },
            itemStyle: { color: '#409eff' },
            lineStyle: { color: '#409eff' }
        }
    ]
}))

const planPieOption = computed(() => ({
    tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
    legend: { orient: 'vertical', right: 10, top: 'middle' },
    series: [
        {
            type: 'pie',
            radius: ['35%', '65%'],
            center: ['40%', '50%'],
            data: stats.plan_distribution.map((d) => ({ name: d.name, value: d.count })),
            label: { show: false },
            emphasis: {
                itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.3)' }
            }
        }
    ]
}))

const tenantTrendOption = computed(() => ({
    tooltip: { trigger: 'axis' },
    grid: { left: 40, right: 20, top: 20, bottom: 40 },
    xAxis: {
        type: 'category',
        data: stats.tenant_trend.map((d) => d.date.slice(5)) // MM-DD
    },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
        {
            type: 'bar',
            data: stats.tenant_trend.map((d) => d.count),
            itemStyle: { color: '#409eff' },
            barMaxWidth: 40
        }
    ]
}))

// ─── Data loading ─────────────────────────────────────────────────────────────
async function loadAll() {
    loading.value = true
    try {
        const [extRes, trendRes] = await Promise.all([
            dashboardApi.extendedStats(),
            dashboardApi.revenueTrend(12)
        ])
        const d = extRes.data as typeof stats
        Object.assign(stats, {
            tenant_total: d.tenant_total ?? 0,
            tenant_active: d.tenant_active ?? 0,
            tenant_expiring: d.tenant_expiring ?? 0,
            expiring_7d: d.expiring_7d ?? 0,
            expiring_30d: d.expiring_30d ?? 0,
            plan_total: d.plan_total ?? 0,
            plugin_total: d.plugin_total ?? 0,
            admin_total: d.admin_total ?? 0,
            monthly_revenue: d.monthly_revenue ?? 0,
            last_month_revenue: d.last_month_revenue ?? 0,
            yearly_revenue: d.yearly_revenue ?? 0,
            plan_distribution: Array.isArray(d.plan_distribution) ? d.plan_distribution : [],
            storage_top10: Array.isArray(d.storage_top10) ? d.storage_top10 : [],
            tenant_trend: Array.isArray(d.tenant_trend) ? d.tenant_trend : []
        })
        revenueTrendData.value = Array.isArray(trendRes.data) ? trendRes.data : []
    } catch {
        // 响应拦截器已弹 toast
    } finally {
        loading.value = false
    }
}

onMounted(loadAll)
</script>

<style scoped lang="scss">
.platform-dashboard {
    min-height: 100%;
}

.stat-card {
    .stat-label {
        font-size: 13px;
        color: #909399;
        margin-bottom: 8px;
    }
    .stat-value {
        font-size: 28px;
        font-weight: 700;
    }
}

.chart-box {
    height: 280px;
    width: 100%;
}

.chart-empty {
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c0c4cc;
    font-size: 14px;
}
</style>
