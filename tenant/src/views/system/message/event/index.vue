<template>
    <div class="message-event">
        <el-card shadow="never" class="mb-4">
            <template #header>
                <span class="font-semibold text-base">消息事件配置</span>
            </template>
            <p class="text-sm text-gray-500">
                为每个业务事件配置对应的消息模板，系统将在事件触发时自动发送通知。
            </p>
        </el-card>

        <div v-loading="loading">
            <el-card v-for="event in eventList" :key="event.event_code" shadow="never" class="mb-4">
                <template #header>
                    <div class="flex items-center gap-2">
                        <el-tag type="primary" size="small">{{ event.event_code }}</el-tag>
                        <span class="font-medium">{{ event.label }}</span>
                        <span class="text-sm text-gray-400 ml-2">
                            变量：{{ event.variables.join('、') }}
                        </span>
                    </div>
                </template>

                <el-table :data="getChannelRows(event)" border size="small">
                    <el-table-column label="通道" width="130">
                        <template #default="{ row }">
                            <el-tag :type="channelTagType[row.channel]" size="small">
                                {{ channelLabel[row.channel] }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="启用" width="90" align="center">
                        <template #default="{ row }">
                            <el-switch
                                v-model="row.config.is_enabled"
                                :active-value="1"
                                :inactive-value="0"
                                @change="handleSave(event.event_code, row.channel, row.config)"
                            />
                        </template>
                    </el-table-column>

                    <el-table-column label="关联模板">
                        <template #default="{ row }">
                            <el-select
                                v-model="row.config.template_id"
                                placeholder="请选择消息模板"
                                clearable
                                filterable
                                style="width: 280px"
                                @change="handleSave(event.event_code, row.channel, row.config)"
                            >
                                <el-option
                                    v-for="tpl in templateList"
                                    :key="tpl.id"
                                    :label="`[${tpl.code}] ${tpl.name}`"
                                    :value="tpl.id"
                                />
                            </el-select>
                        </template>
                    </el-table-column>

                    <el-table-column label="变量映射" min-width="200">
                        <template #default="{ row }">
                            <div class="text-xs text-gray-400">
                                <template
                                    v-if="Object.keys(row.config.variable_mapping || {}).length > 0"
                                >
                                    <el-tag
                                        v-for="(src, tgt) in row.config.variable_mapping"
                                        :key="tgt"
                                        size="small"
                                        class="mr-1 mb-1"
                                    >
                                        {{ tgt }} ← {{ src }}
                                    </el-tag>
                                </template>
                                <span v-else class="text-gray-300">—（直接透传）</span>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>
    </div>
</template>

<script setup lang="ts" name="MessageEvent">
import { ElMessage } from 'element-plus'
import { onMounted, ref } from 'vue'

import { messageTemplateApi } from '@/api/message'
import {
    messageEventApi,
    type MessageEventChannelConfig,
    type MessageEventItem
} from '@/api/message-event'

// ---- 数据 ----
const loading = ref(false)
const eventList = ref<MessageEventItem[]>([])
const templateList = ref<any[]>([])

const channelLabel: Record<string, string> = {
    sms: '短信',
    wechat_oa: '公众号',
    wechat_miniapp: '小程序'
}
type ElTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'
const channelTagType: Record<string, ElTagType> = {
    sms: 'primary',
    wechat_oa: 'success',
    wechat_miniapp: 'warning'
}

// 将 event.channels 对象展开为 table 所需的行数组
const getChannelRows = (event: MessageEventItem) => {
    return (['sms', 'wechat_oa', 'wechat_miniapp'] as const).map((ch) => ({
        channel: ch,
        config: event.channels[ch] as MessageEventChannelConfig
    }))
}

// ---- 加载 ----
const loadEvents = async () => {
    loading.value = true
    try {
        const res = await messageEventApi.list()
        eventList.value = (res as any)?.data ?? res ?? []
    } catch {
        ElMessage.error('加载事件配置失败')
    } finally {
        loading.value = false
    }
}

const loadTemplates = async () => {
    try {
        const res = await messageTemplateApi.getList({ page: 1, limit: 200, status: 1 })
        templateList.value = (res as any)?.data?.list ?? []
    } catch {
        // 静默失败，模板列表只影响选择器
    }
}

onMounted(() => {
    loadEvents()
    loadTemplates()
})

// ---- 保存 ----
const handleSave = async (
    eventCode: string,
    channel: string,
    config: MessageEventChannelConfig
) => {
    try {
        await messageEventApi.update(eventCode, channel, {
            template_id: config.template_id,
            is_enabled: config.is_enabled,
            variable_mapping: config.variable_mapping
        })
        ElMessage.success('保存成功')
    } catch (e: any) {
        ElMessage.error(e?.message || '保存失败')
    }
}
</script>

<style lang="scss" scoped>
.message-event {
    .mb-4 {
        margin-bottom: 16px;
    }
    .font-semibold {
        font-weight: 600;
    }
    .text-base {
        font-size: 14px;
    }
    .text-sm {
        font-size: 13px;
    }
    .text-xs {
        font-size: 12px;
    }
    .text-gray-400 {
        color: #9ca3af;
    }
    .text-gray-300 {
        color: #d1d5db;
    }
    .text-gray-500 {
        color: #6b7280;
    }
    .font-medium {
        font-weight: 500;
    }
    .flex {
        display: flex;
    }
    .items-center {
        align-items: center;
    }
    .gap-2 {
        gap: 8px;
    }
    .ml-2 {
        margin-left: 8px;
    }
    .mr-1 {
        margin-right: 4px;
    }
    .mb-1 {
        margin-bottom: 4px;
    }
}
</style>
