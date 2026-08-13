<template>
    <el-popover placement="bottom" :width="380" trigger="click" @show="loadAnnouncements">
        <template #reference>
            <el-badge :value="unreadCount" :hidden="unreadCount === 0" :max="99" class="mr-4">
                <div
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer"
                >
                    <el-tooltip effect="dark" content="平台公告" placement="bottom">
                        <Icon name="i-svg:bell-ring" />
                    </el-tooltip>
                </div>
            </el-badge>
        </template>

        <div class="announcement-popover">
            <div class="popover-header">
                <span class="title">平台公告</span>
            </div>

            <el-scrollbar max-height="380px">
                <div v-if="announcements.length === 0" class="empty-text">暂无公告</div>
                <div
                    v-for="item in announcements"
                    :key="item.id"
                    class="announcement-item"
                    :class="{ unread: !item.is_read }"
                    @click="handleView(item)"
                >
                    <div class="item-left">
                        <div class="item-dot" :class="{ active: !item.is_read }"></div>
                    </div>
                    <div class="item-content">
                        <div class="item-header">
                            <span class="item-title">{{ item.title }}</span>
                            <el-tag
                                :type="typeTagMap[item.type] || 'info'"
                                size="small"
                                class="item-tag"
                            >
                                {{ item.type_text }}
                            </el-tag>
                        </div>
                        <div class="item-time">{{ formatTime(item.published_at) }}</div>
                    </div>
                </div>
            </el-scrollbar>
        </div>

        <!-- Detail drawer -->
        <el-drawer
            v-model="detailVisible"
            :title="currentAnnouncement?.title"
            size="520px"
            direction="rtl"
            :append-to-body="true"
        >
            <div v-if="currentAnnouncement" class="detail-content">
                <div class="detail-meta">
                    <el-tag :type="typeTagMap[currentAnnouncement.type] || 'info'" size="small">
                        {{ currentAnnouncement.type_text }}
                    </el-tag>
                    <span class="detail-time">{{ currentAnnouncement.published_at }}</span>
                </div>
                <el-divider />
                <div class="detail-body" style="white-space: pre-wrap; line-height: 1.7">
                    {{ currentAnnouncement.content }}
                </div>
            </div>
        </el-drawer>
    </el-popover>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { platformAnnouncementApi } from '@/api/platform-announcement'

const unreadCount = ref(0)
const announcements = ref<any[]>([])
const detailVisible = ref(false)
const currentAnnouncement = ref<any>(null)

type ElTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'
const typeTagMap: Record<string, ElTagType> = {
    notice: 'primary',
    update: 'success',
    warning: 'warning'
}

const formatTime = (time: string) => {
    if (!time) return ''
    const d = new Date(time)
    const now = new Date()
    const diff = now.getTime() - d.getTime()
    if (diff < 60000) return '刚刚'
    if (diff < 3600000) return Math.floor(diff / 60000) + ' 分钟前'
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' 小时前'
    return d.toLocaleDateString('zh-CN')
}

const fetchUnreadCount = async () => {
    try {
        const res = await platformAnnouncementApi.unreadCount()
        unreadCount.value = res.data?.count || 0
    } catch {
        // silent
    }
}

const loadAnnouncements = async () => {
    try {
        const res = await platformAnnouncementApi.list({ page: 1, limit: 10 })
        const rawList = res.data?.list || []
        // mark which ones are read based on unread count (server side handles this)
        announcements.value = rawList
    } catch {
        // silent
    }
}

const handleView = async (item: any) => {
    try {
        const res = await platformAnnouncementApi.show(item.id)
        currentAnnouncement.value = res.data
        detailVisible.value = true
        // After viewing, refresh unread count
        fetchUnreadCount()
        // Mark as read locally
        item.is_read = true
    } catch {
        // silent
    }
}

onMounted(() => {
    fetchUnreadCount()
    // Refresh unread count every minute
    setInterval(fetchUnreadCount, 60000)
})
</script>

<style lang="scss" scoped>
.announcement-popover {
    margin: -12px;

    .popover-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--el-border-color-lighter);

        .title {
            font-weight: 600;
            font-size: 14px;
            color: var(--el-text-color-primary);
        }
    }

    .empty-text {
        text-align: center;
        padding: 40px 0;
        color: var(--el-text-color-placeholder);
        font-size: 14px;
    }

    .announcement-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 16px;
        cursor: pointer;
        transition: background-color 0.2s;

        &:hover {
            background-color: var(--el-color-primary-light-9);
        }

        &.unread {
            background-color: var(--color-brand-ghost);
        }

        .item-left {
            padding-top: 6px;
            flex-shrink: 0;
        }

        .item-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--el-border-color-lighter);

            &.active {
                background-color: var(--el-color-primary);
            }
        }

        .item-content {
            flex: 1;
            min-width: 0;

            .item-header {
                display: flex;
                align-items: center;
                gap: 8px;

                .item-title {
                    flex: 1;
                    font-size: 13px;
                    color: var(--el-text-color-primary);
                    line-height: 1.5;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .item-tag {
                    flex-shrink: 0;
                }
            }

            .item-time {
                font-size: 12px;
                color: var(--el-text-color-placeholder);
                margin-top: 4px;
            }
        }
    }
}

.detail-content {
    padding: 0 4px;

    .detail-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;

        .detail-time {
            font-size: 13px;
            color: var(--el-text-color-secondary);
        }
    }

    .detail-body {
        font-size: 14px;
        color: var(--el-text-color-primary);
    }
}
</style>
