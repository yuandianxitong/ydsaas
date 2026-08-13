<script setup lang="ts">
import { ElButton, ElMessage, ElMessageBox, ElTag } from 'element-plus'
import { computed, ref } from 'vue'

import { marketplaceApi, type MarketplaceAppRow } from '@/api/marketplace'

import ChangelogDrawer from './ChangelogDrawer.vue'
import LicenseStatusBadge from './LicenseStatusBadge.vue'

const props = defineProps<{ row: MarketplaceAppRow }>()
const emit = defineEmits<{
    changed: []
    install: [row: MarketplaceAppRow]
}>()

const state = computed<'public' | 'purchasable' | 'install' | 'upgrade' | 'installed'>(() => {
    // 未连接浏览态：不知拥有关系，按钮「安装」触发连接对话框
    if (props.row.is_public) return 'public'
    // 已连接但未购：跳官网购买
    if (!props.row.owned) return 'purchasable'
    if (!props.row.installed) return 'install'
    if (props.row.has_upgrade) return 'upgrade'
    return 'installed'
})

// v2.9.0: 兼容性 / changelog
const isIncompatible = computed(() => props.row.compatible === 0 || props.row.compatible === false)
const isExpired = computed(() => props.row.license_status === 'expired')
const showChangelog = ref(false)

function openChangelog() {
    showChangelog.value = true
}

// 点击「安装」只上抛意图，由面板统一编排（未连接→连接账号；已连接→验证权益→确认）
function onInstall() {
    emit('install', props.row)
}

// 未拥有应用：新标签页打开官网购买页（buy_url 由后端按 web_base 派生）
function onPurchase() {
    if (props.row.buy_url) {
        window.open(props.row.buy_url, '_blank', 'noopener')
    }
}

async function onUpgrade() {
    if (!props.row.plugin_id) return
    try {
        await ElMessageBox.confirm(`确认升级到 v${props.row.latest_version}？`, '提示', {
            type: 'warning'
        })
        await marketplaceApi.upgrade({ plugin_id: props.row.plugin_id })
        ElMessage.success('升级成功')
        emit('changed')
    } catch (e: any) {
        if (e !== 'cancel') ElMessage.error(e?.message || '升级失败')
    }
}
</script>

<template>
    <div class="app-card">
        <div class="icon-wrap">
            <img :src="row.app_icon_url || '/default-icon.png'" class="icon" alt="" />
        </div>
        <div class="meta">
            <h3 class="title">{{ row.app_name }}</h3>
            <p class="sub">
                <ElTag
                    v-if="row.owned && !row.installed"
                    type="success"
                    effect="plain"
                    size="small"
                    class="owned-badge"
                >
                    已拥有
                </ElTag>
                <template v-if="row.latest_version">
                    <span>v{{ row.latest_version }}</span>
                </template>
                <template v-if="row.installed_version && row.installed">
                    <span v-if="row.latest_version" class="dot">·</span>
                    <span class="installed-tag">已装 v{{ row.installed_version }}</span>
                </template>
                <LicenseStatusBadge
                    v-if="row.installed"
                    :status="row.license_status"
                    :grace-started-at="row.license_grace_started_at"
                />
                <ElTag
                    v-if="row.has_upgrade && !isExpired && row.installed"
                    type="warning"
                    effect="dark"
                    size="small"
                    class="upgrade-badge"
                    @click.stop="openChangelog"
                >
                    v{{ row.installed_version }} → v{{ row.latest_version }}
                </ElTag>
            </p>
            <p v-if="row.app_description" class="desc">{{ row.app_description }}</p>
            <p v-else class="desc placeholder">{{ row.app_code }}</p>
        </div>
        <div class="action">
            <ElButton v-if="state === 'purchasable'" type="success" round plain @click="onPurchase">
                购买
            </ElButton>
            <ElButton v-else-if="state === 'public'" type="primary" round @click="onInstall">
                安装
            </ElButton>
            <ElButton
                v-else-if="state === 'install'"
                type="primary"
                round
                :disabled="isIncompatible"
                :title="isIncompatible ? '与当前 framework-saas 版本不兼容' : ''"
                @click="onInstall"
            >
                安装
            </ElButton>
            <ElButton
                v-else-if="state === 'upgrade'"
                type="warning"
                round
                :disabled="isExpired"
                :title="isExpired ? '授权已过期，无法升级' : ''"
                @click="onUpgrade"
            >
                升级到 v{{ row.latest_version }}
            </ElButton>
            <ElTag v-else type="success" effect="light" round>已安装</ElTag>
        </div>
        <ChangelogDrawer
            v-if="row.entitlement_code && row.latest_version"
            v-model="showChangelog"
            :entitlement-code="row.entitlement_code"
            :app-name="row.app_name"
            :from-version="row.installed_version || ''"
            :to-version="row.latest_version"
        />
    </div>
</template>

<style scoped>
.app-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 18px;
    background: linear-gradient(135deg, #fafbfc 0%, #f3f5f9 100%);
    border: 1px solid #ebeef5;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.app-card:hover {
    border-color: #c6cbd4;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
}

.icon-wrap {
    flex: 0 0 auto;
    width: 56px;
    height: 56px;
    background: #ffffff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.icon {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.meta {
    flex: 1;
    min-width: 0;
}

.title {
    margin: 0 0 4px 0;
    font-size: 15px;
    font-weight: 600;
    color: #1f2329;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sub {
    margin: 0 0 4px 0;
    font-size: 12px;
    color: #8a8f99;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.dot {
    color: #c6cbd4;
}

.installed-tag {
    color: #67c23a;
}

.desc {
    margin: 0;
    font-size: 13px;
    color: #5f6772;
    line-height: 1.5;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.desc.placeholder {
    color: #a8acb3;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
}

.action {
    flex: 0 0 auto;
    margin-left: 8px;
}

.upgrade-badge {
    cursor: pointer;
}
</style>
