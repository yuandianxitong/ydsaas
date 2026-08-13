<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { pluginApi, type TenantPluginInfo } from '@/api/plugin'
import feedback from '@/utils/feedback'

const props = defineProps<{ data: TenantPluginInfo }>()
const emit = defineEmits<{
    (e: 'click'): void
    (e: 'purchase'): void
    (e: 'refresh'): void
}>()

const { t } = useI18n()

// type===4 means ADDON（按需购买的增值插件——这种保留购买入口）
const ADDON_TYPE = 4
// 已过期 — 唯一需要主动展示给租户的"异常状态"
const EXPIRED = 3

const importingTestdata = ref(false)

async function handleImportTestdata() {
    try {
        await feedback.confirm(t('plugin.importTestdataConfirm'))
    } catch {
        return
    }
    importingTestdata.value = true
    try {
        const res = await pluginApi.importTestdata(props.data.plugin_id)
        ElMessage.success(t('plugin.importTestdataDone', { count: res.data.imported }))
        emit('refresh')
    } finally {
        importingTestdata.value = false
    }
}

// 图标渐变取自原型 ICON_COLORS（from → to），按 plugin_code 哈希确定性取色
function gradient(code: string): string {
    const palette = [
        '#5B8DEF,#2C73FF', // blue
        '#6C8AF7,#3B5BDB', // indigo
        '#A875FF,#7C3AED', // violet
        '#FF7A95,#E11D48', // rose
        '#52C896,#16A34A', // green
        '#F4A030,#E37A00', // amber
        '#5BC9DD,#0EA5E9' // teal
    ]
    let h = 0
    for (let i = 0; i < code.length; i++) h = (h * 31 + code.charCodeAt(i)) >>> 0
    return palette[h % palette.length]
}
</script>

<template>
    <el-card shadow="never" class="plugin-card" @click="emit('click')">
        <div class="card-head">
            <div
                class="icon-block"
                :class="{ 'icon-block--has-image': !!data.icon }"
                :style="
                    data.icon
                        ? undefined
                        : { background: `linear-gradient(135deg, ${gradient(data.plugin_code)})` }
                "
            >
                <img v-if="data.icon" :src="data.icon" />
                <span v-else>{{ data.name?.[0] }}</span>
            </div>
            <div class="meta">
                <div class="title">
                    {{ data.name }}
                    <el-tag
                        v-if="data.tenant_status === EXPIRED"
                        size="small"
                        type="danger"
                        effect="plain"
                    >
                        已过期
                    </el-tag>
                </div>
                <div class="desc">{{ data.description }}</div>
            </div>
        </div>
        <!-- 仅 ADDON 类型 + 未启用/已过期 才显示购买按钮；已安装且带演示数据的插件显示导入按钮
             （租户能真正控制的操作） -->
        <div
            v-if="(data.type === ADDON_TYPE && data.tenant_status !== 1) || data.has_testdata"
            class="card-foot"
        >
            <el-button
                v-if="data.type === ADDON_TYPE && data.tenant_status !== 1"
                size="small"
                type="primary"
                plain
                @click.stop="emit('purchase')"
            >
                购买
            </el-button>
            <el-button
                v-if="data.has_testdata"
                v-perms="'plugin.testdata'"
                size="small"
                text
                :disabled="!!data.testdata_imported_at || importingTestdata"
                @click.stop="handleImportTestdata"
            >
                {{
                    data.testdata_imported_at
                        ? t('plugin.testdataImported')
                        : t('plugin.importTestdata')
                }}
            </el-button>
        </div>
    </el-card>
</template>

<style scoped>
.plugin-card {
    cursor: pointer;
    /* 无描边：hover 用蓝调投影 + 浅蓝底提供反馈 */
    transition: box-shadow 0.15s ease, background 0.15s ease;
}
.plugin-card:hover {
    background: #fafbff;
    box-shadow: 0 4px 12px -8px rgba(44, 115, 255, 0.35);
}
.card-head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.icon-block {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
    font-size: 20px;
    flex-shrink: 0;
    overflow: hidden;
}
/* v2.7.5：有真实图标时去掉渐变背景，让 img 填满整块 */
.icon-block.icon-block--has-image {
    background: transparent;
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
    display: flex;
    align-items: center;
    gap: 8px;
}
.desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    line-height: 1.5;
    margin-top: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.card-foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-top: 10px;
    margin-top: 10px;
    border-top: 1px dashed var(--color-border);
}
</style>
