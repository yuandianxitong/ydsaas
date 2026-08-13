<script setup lang="ts">
import { MoreFilled } from '@element-plus/icons-vue'

import type { PluginInfo } from '@/types/api'

defineProps<{ data: PluginInfo }>()
defineEmits<{
    (e: 'install'): void
    (e: 'uninstall'): void
    (e: 'upgrade'): void
    (e: 'purge'): void
    (e: 'grant'): void
    (e: 'disable'): void
    (e: 'enable'): void
}>()

const statusMap: Record<number, { label: string; cls: string }> = {
    0: { label: '未安装', cls: 'tag-info' },
    1: { label: '已启用', cls: 'tag-success' },
    2: { label: '已禁用', cls: 'tag-info' },
    3: { label: '安装中', cls: 'tag-warning' },
    4: { label: '升级中', cls: 'tag-warning' },
    5: { label: '安装失败', cls: 'tag-danger' },
    6: { label: '已卸载', cls: 'tag-info' }
}

function gradient(code: string): string {
    const palette = [
        '#5b8def,#3a6dd9',
        '#ff7849,#ee5a36',
        '#26c281,#1aa86e',
        '#a855f7,#7c3aed',
        '#f59e0b,#d97706'
    ]
    let h = 0
    for (let i = 0; i < code.length; i++) h = (h * 31 + code.charCodeAt(i)) >>> 0
    return palette[h % palette.length]
}
</script>

<template>
    <el-card shadow="hover" class="plugin-card">
        <span class="kind-badge" :class="data.kind === 'app' ? 'kind-app' : 'kind-plugin'">
            {{ data.kind === 'app' ? '应用' : '插件' }}
        </span>
        <div class="card-head">
            <div
                class="icon-block"
                :class="{ 'icon-block--has-image': !!data.icon }"
                :style="
                    data.icon
                        ? undefined
                        : { background: `linear-gradient(135deg, ${gradient(data.code)})` }
                "
            >
                <img v-if="data.icon" :src="data.icon" />
                <span v-else>{{ data.name?.[0] }}</span>
            </div>
            <div class="meta">
                <div class="title">{{ data.name }}</div>
                <div class="desc">{{ data.description }}</div>
            </div>
            <!-- 未安装（磁盘扫出的虚拟行）：直接给一个亮蓝"安装"按钮，不用下拉 -->
            <el-button
                v-if="data.status === 0"
                type="primary"
                size="small"
                class="actions actions-install"
                @click="$emit('install')"
                >安装</el-button
            >
            <el-dropdown v-else trigger="click" class="actions">
                <el-icon><MoreFilled /></el-icon>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item
                            v-if="[3, 5, 6].includes(data.status)"
                            @click="$emit('install')"
                            >安装</el-dropdown-item
                        >
                        <el-dropdown-item v-if="data.status === 1" @click="$emit('upgrade')"
                            >升级</el-dropdown-item
                        >
                        <el-dropdown-item v-if="data.status === 1" @click="$emit('grant')"
                            >授权管理</el-dropdown-item
                        >
                        <el-dropdown-item v-if="data.status === 1" @click="$emit('disable')"
                            >禁用</el-dropdown-item
                        >
                        <el-dropdown-item v-if="data.status === 2" @click="$emit('enable')"
                            >启用</el-dropdown-item
                        >
                        <el-dropdown-item
                            v-if="[1, 2].includes(data.status)"
                            @click="$emit('uninstall')"
                            >卸载</el-dropdown-item
                        >
                        <el-dropdown-item
                            v-if="data.status === 6 || !!data.deleted_at"
                            @click="$emit('purge')"
                            >清理数据</el-dropdown-item
                        >
                    </el-dropdown-menu>
                </template>
            </el-dropdown>
        </div>
        <div class="card-foot">
            <span class="label">版本</span>
            <span class="value">v{{ data.version }}</span>
            <el-tag
                v-if="data.status === 1 && data.update_available === 1 && data.latest_version"
                size="small"
                type="warning"
                style="margin-left: 8px"
            >
                可升级 v{{ data.latest_version }}
            </el-tag>
            <el-tag
                size="small"
                :type="statusMap[data.status]?.cls === 'tag-danger' ? 'danger' : 'info'"
                style="margin-left: auto"
            >
                {{ statusMap[data.status]?.label || '-' }}
            </el-tag>
        </div>
    </el-card>
</template>

<style scoped>
.plugin-card {
    position: relative;
    height: 100%;
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    border-radius: 6px;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.94)),
        var(--color-surface);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    transition:
        transform var(--motion-duration-base) var(--motion-easing),
        box-shadow var(--motion-duration-base) var(--motion-easing),
        border-color var(--motion-duration-base) var(--motion-easing);
}
.plugin-card:hover {
    border-color: rgba(11, 81, 183, 0.18) !important;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.1);
    transform: translateY(-2px);
}
.plugin-card :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 0;
}
.card-head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    /* top: 28px 给左上角 kind-badge 让位，避免压住 icon */
    padding: 28px 16px 14px;
}
.icon-block {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
    font-size: 20px;
    flex-shrink: 0;
    overflow: hidden;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.22),
        0 8px 16px rgba(15, 23, 42, 0.14);
}
/* v2.7.5：有真实图标时去掉渐变背景与内阴影，让 img 填满整块 */
.icon-block.icon-block--has-image {
    background: transparent;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.14);
}
.icon-block img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
.meta {
    flex: 1;
    min-width: 0;
}
.title {
    font-weight: 600;
    color: var(--el-text-color-primary);
    line-height: 1.35;
}
.desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    line-height: 1.5;
    margin-top: 2px;
    /* 预留 2 行高度，避免同行内 desc 行数不一导致 card 被拉伸出大留白 */
    min-height: 36px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.actions {
    cursor: pointer;
    color: var(--el-text-color-placeholder);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    transition:
        background-color var(--motion-duration-fast) var(--motion-easing),
        color var(--motion-duration-fast) var(--motion-easing);
}
.actions:hover {
    color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}
/* 未安装的「安装」按钮：用 el-button 默认外观，不要继承 .actions 的方框 hover */
.actions-install {
    width: auto;
    height: auto;
    padding: 4px 12px;
}
.actions-install:hover {
    background: var(--el-color-primary);
    color: #fff;
}
.card-foot {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    margin-top: auto;
    border-top: 1px dashed var(--el-border-color-lighter);
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
.value {
    font-weight: 500;
    color: var(--el-text-color-regular);
}
.kind-badge {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1;
    padding: 2px 10px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.5;
    color: #fff;
    border-bottom-right-radius: 6px;
    letter-spacing: 0.5px;
}
.kind-app {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}
.kind-plugin {
    background: linear-gradient(135deg, #a855f7, #7c3aed);
}
</style>
