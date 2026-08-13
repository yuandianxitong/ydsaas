<script setup lang="ts">
import { Check, InfoFilled } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { pluginGrantApi } from '@/api/plugin-grant'
import type { PluginOptionInfo } from '@/types/api'

const { t } = useI18n()

const props = defineProps<{ planId: number | null }>()

const options = ref<PluginOptionInfo[]>([])
const selectedIds = defineModel<number[]>('selectedIds', { default: () => [] })
const autoEnableIds = defineModel<number[]>('autoEnableIds', { default: () => [] })
const loading = ref(false)

async function loadOptions() {
    const res = await pluginGrantApi.options()
    options.value = res.data
}

async function loadCurrentGrants() {
    if (!props.planId) {
        selectedIds.value = []
        autoEnableIds.value = []
        return
    }
    loading.value = true
    try {
        const res = await pluginGrantApi.listByPlan(props.planId)
        const list = Array.isArray(res?.data) ? res.data : []
        selectedIds.value = list.map((g) => g.plugin_id)
        autoEnableIds.value = list.filter((g) => g.auto_enable === 1).map((g) => g.plugin_id)
    } finally {
        loading.value = false
    }
}

/** 供父组件主动调的"保存方法"，PlanForm 在保存套餐后调用 */
async function commit(planId: number) {
    await pluginGrantApi.sync(planId, {
        plugin_ids: selectedIds.value,
        auto_enable_ids: autoEnableIds.value
    })
    ElMessage.success(t('pluginMgr.grantSynced'))
}

defineExpose({ commit, reload: loadCurrentGrants })

onMounted(loadOptions)
watch(() => props.planId, loadCurrentGrants, { immediate: true })

function isSelected(id: number): boolean {
    return selectedIds.value.includes(id)
}

function toggle(id: number) {
    if (isSelected(id)) {
        selectedIds.value = selectedIds.value.filter((v) => v !== id)
        // 取消授权时连带清掉自动启用
        autoEnableIds.value = autoEnableIds.value.filter((v) => v !== id)
    } else {
        selectedIds.value = [...selectedIds.value, id]
    }
}

function toggleAuto(id: number, on: boolean) {
    autoEnableIds.value = on
        ? [...new Set([...autoEnableIds.value, id])]
        : autoEnableIds.value.filter((v) => v !== id)
}

/** 与 PluginCard 同款：无图标时按 code 哈希取渐变底色 */
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
    <div v-loading="loading" class="plugin-grant-panel">
        <el-divider content-position="left">{{ $t('pluginMgr.grantSection') }}</el-divider>
        <p class="grant-hint">
            <el-icon><InfoFilled /></el-icon>
            {{ $t('pluginMgr.grantHint') }}
        </p>

        <div v-if="options.length" class="plugin-grid">
            <div
                v-for="o in options"
                :key="o.id"
                class="plugin-item"
                :class="{ 'is-selected': isSelected(o.id) }"
                @click="toggle(o.id)"
            >
                <div class="item-main">
                    <div
                        class="icon"
                        :class="{ 'has-image': !!o.icon }"
                        :style="
                            o.icon
                                ? undefined
                                : { background: `linear-gradient(135deg, ${gradient(o.code)})` }
                        "
                    >
                        <img v-if="o.icon" :src="o.icon" />
                        <span v-else>{{ o.name?.[0] }}</span>
                    </div>
                    <div class="meta">
                        <div class="name">{{ o.name }}</div>
                        <div class="caption">{{ o.code }} · v{{ o.version }}</div>
                    </div>
                    <span class="check">
                        <el-icon><Check /></el-icon>
                    </span>
                </div>
                <div class="item-foot" @click.stop>
                    <span class="foot-label">{{ $t('pluginMgr.autoEnable') }}</span>
                    <el-switch
                        size="small"
                        :disabled="!isSelected(o.id)"
                        :model-value="autoEnableIds.includes(o.id)"
                        @change="(v: string | number | boolean) => toggleAuto(o.id, !!v)"
                    />
                </div>
            </div>
        </div>
        <el-empty v-else :description="$t('pluginMgr.noPlugins')" :image-size="72" />

        <p v-if="options.length && !selectedIds.length" class="grant-empty-hint">
            {{ $t('pluginMgr.grantEmptyHint') }}
        </p>
    </div>
</template>

<style scoped lang="scss">
.grant-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0 0 12px;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.plugin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(215px, 1fr));
    gap: 10px;
}

.plugin-item {
    border: 1px solid var(--color-divider);
    /* 与 PluginCard 一致的 6px，不走大圆角 */
    border-radius: 6px;
    cursor: pointer;
    user-select: none;
    overflow: hidden;
    transition:
        border-color var(--motion-duration-fast) var(--motion-easing),
        box-shadow var(--motion-duration-fast) var(--motion-easing);

    &:hover {
        border-color: var(--el-color-primary-light-5);
    }

    .item-main {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
    }

    .icon {
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-sm);
        overflow: hidden;
        color: #fff;
        font-weight: 600;
        font-size: 16px;

        &.has-image {
            background: transparent;
        }

        img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
    }

    .meta {
        flex: 1;
        min-width: 0;

        .name {
            font-size: 13px;
            font-weight: 500;
            color: var(--el-text-color-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .caption {
            font-size: 12px;
            color: var(--el-text-color-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }

    /* 选中指示：未选中为空心圆，选中变品牌实心带 √ */
    .check {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 1px solid var(--color-border);
        color: transparent;
        font-size: 12px;
        transition: all var(--motion-duration-fast) var(--motion-easing);
    }

    .item-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 12px;
        border-top: 1px dashed var(--color-divider);
        cursor: default;

        .foot-label {
            font-size: 12px;
            color: var(--el-text-color-secondary);
        }
    }

    &.is-selected {
        /* 外描边而非内阴影：内阴影会被 item-foot 的背景色盖住，
         * 外圈 ring 始终包住整卡（含 foot 区） */
        border-color: var(--el-color-primary);
        box-shadow: 0 0 0 1px var(--el-color-primary);

        .check {
            background: var(--el-color-primary);
            border-color: var(--el-color-primary);
            color: #fff;
        }

        .item-foot {
            background: var(--el-color-primary-light-9);
        }
    }
}

.grant-empty-hint {
    margin: 12px 0 0;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>
