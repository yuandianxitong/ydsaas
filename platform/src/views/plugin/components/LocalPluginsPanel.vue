<script setup lang="ts">
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, ref } from 'vue'

import { pluginApi } from '@/api/plugin'
import type { PluginInfo } from '@/types/api'

import PluginGrantPanel from '../../plan/components/PluginGrantPanel.vue'
import PluginCard from './PluginCard.vue'
import UpgradeDialog from './UpgradeDialog.vue'
import UploadDialog from './UploadDialog.vue'

type Filter = 'all' | 'app' | 'plugin'

const all = ref<PluginInfo[]>([])
const filter = ref<Filter>('all')
const page = ref(1)
const limit = ref(20)
const loading = ref(false)
const uploadVisible = ref(false)
const upgradeVisible = ref(false)
const grantVisible = ref(false)
const currentForUpgrade = ref({ id: 0, code: '', version: '' })

async function load() {
    loading.value = true
    try {
        // 一次性拉全部（管理端本地插件量级 <100，足够），后续在前端切片
        const res = await pluginApi.list({ page: 1, limit: 999 })
        all.value = res.data.list
    } finally {
        loading.value = false
    }
}

const filtered = computed(() =>
    filter.value === 'all' ? all.value : all.value.filter((r) => r.kind === filter.value)
)

const total = computed(() => filtered.value.length)

const pageList = computed(() => {
    const start = (page.value - 1) * limit.value
    return filtered.value.slice(start, start + limit.value)
})

function onFilterChange(v: Filter) {
    filter.value = v
    page.value = 1
}

async function install(row: PluginInfo) {
    // id===0 是磁盘扫到的虚拟行（git 一起带的本地插件），走 install-from-disk
    if (row.id === 0) {
        await pluginApi.installFromDisk(row.code)
    } else {
        await pluginApi.install(row.id)
    }
    ElMessage.success(`插件 ${row.code} 安装成功`)
    await load()
}

async function uninstall(row: PluginInfo) {
    await ElMessageBox.confirm(
        `确定卸载 [${row.code}] 吗？将保留本地代码与业务数据，列表中可再次安装；彻底清数据请用「清理数据」。`,
        '卸载确认',
        { type: 'warning' }
    )
    await pluginApi.uninstall(row.id)
    ElMessage.success(`已卸载 ${row.code}`)
    await load()
}

function openUpgrade(row: PluginInfo) {
    currentForUpgrade.value = { id: row.id, code: row.code, version: row.version }
    upgradeVisible.value = true
}

async function disable(row: PluginInfo) {
    await ElMessageBox.confirm(
        `禁用后所有租户将暂时无法使用 [${row.code}]（代码与数据保留，可随时启用）。继续？`,
        '提示',
        { type: 'warning' }
    )
    await pluginApi.disable(row.id)
    ElMessage.success(`已禁用 ${row.code}`)
    await load()
}

async function enable(row: PluginInfo) {
    await pluginApi.enable(row.id)
    ElMessage.success(`已启用 ${row.code}`)
    await load()
}

async function purge(row: PluginInfo) {
    await ElMessageBox.confirm(`⚠️ 清理 ${row.code} 数据不可恢复！`, '危险操作', {
        type: 'error'
    })
    await pluginApi.purgeData(row.id)
    ElMessage.success('已清理')
    await load()
}

function openGrant(_row: PluginInfo) {
    grantVisible.value = true
}

async function onUploaded(pluginId: number) {
    try {
        await pluginApi.install(pluginId)
        ElMessage.success('插件已安装')
    } catch {
        // install 失败由 axios 拦截器统一弹消息，不必再处理
    }
    await load()
}

onMounted(load)

// 供父组件在「官方市场」安装成功后主动刷新本地列表（否则切回来是旧数据，需手动刷新页面）
defineExpose({ refresh: load })
</script>

<template>
    <div class="local-plugins-panel">
        <div class="panel-header">
            <div class="kind-chips">
                <el-button
                    :type="filter === 'all' ? 'primary' : 'default'"
                    :plain="filter !== 'all'"
                    size="small"
                    round
                    @click="onFilterChange('all')"
                    >全部</el-button
                >
                <el-button
                    :type="filter === 'app' ? 'primary' : 'default'"
                    :plain="filter !== 'app'"
                    size="small"
                    round
                    @click="onFilterChange('app')"
                    >应用</el-button
                >
                <el-button
                    :type="filter === 'plugin' ? 'primary' : 'default'"
                    :plain="filter !== 'plugin'"
                    size="small"
                    round
                    @click="onFilterChange('plugin')"
                    >插件</el-button
                >
            </div>
            <el-button type="primary" :icon="Plus" @click="uploadVisible = true"> 上传 </el-button>
        </div>

        <div class="plugin-list-panel">
            <div
                v-loading="loading"
                class="grid"
                :class="{ 'grid--placeholder': loading || !pageList.length }"
            >
                <PluginCard
                    v-for="row in pageList"
                    :key="`${row.kind}-${row.id || row.code}`"
                    :data="row"
                    @install="install(row)"
                    @uninstall="uninstall(row)"
                    @upgrade="openUpgrade(row)"
                    @purge="purge(row)"
                    @grant="openGrant(row)"
                    @disable="disable(row)"
                    @enable="enable(row)"
                />
                <el-empty
                    v-if="!loading && !pageList.length"
                    :description="
                        filter === 'all'
                            ? '暂无应用或插件'
                            : filter === 'app'
                              ? '暂无应用'
                              : '暂无插件'
                    "
                />
            </div>

            <el-pagination
                v-if="total > limit"
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next, sizes"
                class="pagination"
            />
        </div>

        <UploadDialog v-model:visible="uploadVisible" @uploaded="onUploaded" />
        <UpgradeDialog
            v-model:visible="upgradeVisible"
            :plugin-id="currentForUpgrade.id"
            :plugin-code="currentForUpgrade.code"
            :current-version="currentForUpgrade.version"
            @upgraded="load"
        />
        <el-dialog v-model="grantVisible" class="dialog-xl" :title="$t('pluginMgr.grantTitle')">
            <PluginGrantPanel v-if="grantVisible" :plan-id="null" />
        </el-dialog>
    </div>
</template>

<style scoped>
.local-plugins-panel {
    padding: 18px;
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 40px;
    margin-bottom: 16px;
}

.kind-chips {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0;
}

.plugin-list-panel {
    padding: 8px 0;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

/* 仅加载中/空列表时撑起占位高度；有卡片时行高随内容，
 * 避免 min-height 把行撑高后多余空间变成卡片 head/foot 之间的空隙 */
.grid--placeholder {
    min-height: 180px;
}

.grid :deep(.el-empty) {
    grid-column: 1 / -1;
}

.pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

@media (max-width: 1400px) {
    .grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1000px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .panel-header {
        align-items: stretch;
        flex-direction: column;
        gap: 10px;
        padding: 12px 0;
    }

    .grid {
        grid-template-columns: 1fr;
    }
}
</style>
