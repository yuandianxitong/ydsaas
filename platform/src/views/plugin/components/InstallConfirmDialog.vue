<script setup lang="ts">
import { ElButton, ElDialog, ElTag } from 'element-plus'
import { computed, ref, watch } from 'vue'

import type { InstallResolution, MarketplaceAppRow } from '@/api/marketplace'

const visible = defineModel<boolean>('visible', { default: false })
const props = defineProps<{
    resolution: InstallResolution | null
    // 触发安装的官方市场列表行：其 app_name / app_icon_url 才是完整展示信息，
    // 而 install-intent 精简结果常缺这两项，故名称与图标优先取列表行。
    appRow?: MarketplaceAppRow | null
}>()
const emit = defineEmits<{
    confirm: [entitlementCode: string]
}>()

const status = computed(() => props.resolution?.status ?? null)

const title = computed(() => {
    switch (status.value) {
        case 'ready':
            return '确认安装'
        case 'purchase_required':
            return '尚未购买此应用'
        case 'incompatible':
            return '版本不兼容'
        default:
            return '安装'
    }
})

// 收窄类型供模板安全访问
const readyApp = computed(() =>
    props.resolution?.status === 'ready' ? props.resolution.app : null
)
const incompatible = computed(() =>
    props.resolution?.status === 'incompatible' ? props.resolution : null
)
const currentVersion = computed(() => {
    const r = props.resolution
    return r && (r.status === 'ready' || r.status === 'incompatible') ? r.current_version : ''
})

// 展示信息：名称与图标优先取列表行（官方市场目录的完整数据），
// 精简的 install-intent 结果作兜底；标识固定用 app_code。
const displayCode = computed(() => props.appRow?.app_code || readyApp.value?.app_code || '')
const displayName = computed(
    () => props.appRow?.app_name || readyApp.value?.app_name || displayCode.value
)
const displayIcon = computed(() => props.appRow?.app_icon_url || readyApp.value?.app_icon_url || '')

// 图标降级：无 icon 或加载失败时，用名称/标识首字母做占位块，
// 避免像旧实现那样回退到并不存在的 /default-icon.png（404 空白）。
const iconError = ref(false)
watch(displayIcon, () => {
    iconError.value = false
})
const showIcon = computed(() => !!displayIcon.value && !iconError.value)
const monogram = computed(() => (displayName.value || '?').trim().charAt(0).toUpperCase())

function onConfirm() {
    if (props.resolution?.status === 'ready') {
        emit('confirm', props.resolution.app.entitlement_code || '')
        visible.value = false
    }
}

function onBuy() {
    if (props.resolution?.status === 'purchase_required') {
        window.open(props.resolution.buy_url, '_blank', 'noopener')
        visible.value = false
    }
}

function fmtRange(min: string | null, max: string | null): string {
    if (min && max) return `${min} ~ ${max}`
    if (min) return `≥ ${min}`
    if (max) return `≤ ${max}`
    return '—'
}
</script>

<template>
    <ElDialog v-model="visible" class="dialog-sm" :title="title" align-center>
        <!-- 已有权益且兼容：展示版本/发行方/兼容性，最终确认 -->
        <div v-if="status === 'ready' && readyApp" class="confirm-ready">
            <div class="app-head">
                <img
                    v-if="showIcon"
                    :src="displayIcon"
                    class="app-icon"
                    alt=""
                    @error="iconError = true"
                />
                <div v-else class="app-icon app-icon--ph">{{ monogram }}</div>
                <div class="app-info">
                    <div class="app-name">{{ displayName }}</div>
                    <div class="app-code">{{ displayCode }}</div>
                </div>
            </div>
            <ul class="detail-list">
                <li>
                    <span class="k">版本</span>
                    <span class="v">v{{ readyApp.latest_version || '—' }}</span>
                </li>
                <li>
                    <span class="k">兼容性</span>
                    <span class="v">
                        <ElTag type="success" size="small" effect="light">
                            兼容当前 v{{ currentVersion }}
                        </ElTag>
                    </span>
                </li>
                <li v-if="readyApp.app_description">
                    <span class="k">说明</span>
                    <span class="v desc">{{ readyApp.app_description }}</span>
                </li>
            </ul>
        </div>

        <!-- 未购买：引导前往购买 -->
        <div v-else-if="status === 'purchase_required'" class="confirm-text">
            <p>当前账号尚未购买此应用，无法安装。</p>
            <p class="sub">你可以前往元点官方市场购买后，再回到这里一键安装。</p>
        </div>

        <!-- 版本不兼容：禁止安装并展示所需版本 -->
        <div v-else-if="status === 'incompatible' && incompatible" class="confirm-text">
            <p>该应用与当前 Framework-SaaS 版本不兼容，已禁止安装。</p>
            <ul class="detail-list">
                <li>
                    <span class="k">所需版本</span>
                    <span class="v">{{
                        fmtRange(incompatible.required.min, incompatible.required.max)
                    }}</span>
                </li>
                <li>
                    <span class="k">当前版本</span>
                    <span class="v">v{{ currentVersion }}</span>
                </li>
            </ul>
        </div>

        <template #footer>
            <template v-if="status === 'ready'">
                <ElButton @click="visible = false">取消</ElButton>
                <ElButton type="primary" @click="onConfirm">确认安装</ElButton>
            </template>
            <template v-else-if="status === 'purchase_required'">
                <ElButton @click="visible = false">关闭</ElButton>
                <ElButton type="primary" @click="onBuy">前往购买</ElButton>
            </template>
            <template v-else>
                <ElButton type="primary" @click="visible = false">我知道了</ElButton>
            </template>
        </template>
    </ElDialog>
</template>

<style scoped>
.app-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.app-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    object-fit: contain;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
}

.app-icon--ph {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #6a8cff, #4b6bff);
    text-transform: uppercase;
}

.app-name {
    font-size: 15px;
    font-weight: 600;
    color: #1f2329;
}

.app-code {
    font-size: 12px;
    color: #8a8f99;
}

.detail-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.detail-list li {
    display: flex;
    gap: 12px;
    padding: 7px 0;
    border-top: 1px solid #f0f2f5;
    font-size: 13px;
}

.detail-list .k {
    flex: 0 0 72px;
    color: #8a8f99;
}

.detail-list .v {
    flex: 1;
    color: #1f2329;
}

.detail-list .v.desc {
    color: #5f6772;
    line-height: 1.5;
}

.confirm-text p {
    margin: 0 0 8px;
    font-size: 14px;
    color: #1f2329;
    line-height: 1.6;
}

.confirm-text .sub {
    font-size: 13px;
    color: #8a8f99;
}
</style>
