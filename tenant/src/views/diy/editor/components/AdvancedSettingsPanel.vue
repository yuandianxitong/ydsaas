<template>
    <div v-if="component" class="config-panel">
        <div class="config-section">高级设置</div>
        <div class="config-row">
            <span class="config-label">显示组件</span>
            <div class="config-control">
                <el-switch :model-value="!component.hidden" @change="onVisible" />
                <div class="config-field-hint">关闭后 C 端不渲染；编辑器仍可编辑</div>
            </div>
        </div>
        <div class="config-row config-row--top">
            <span class="config-label">生效端</span>
            <div class="config-control">
                <el-checkbox-group :model-value="platforms" @change="onPlatforms">
                    <el-checkbox value="h5">H5</el-checkbox>
                    <el-checkbox value="mp-weixin">微信小程序</el-checkbox>
                    <el-checkbox value="app">App</el-checkbox>
                </el-checkbox-group>
                <div class="config-field-hint">不选表示全端显示</div>
            </div>
        </div>
        <div class="config-row">
            <span class="config-label">开始时间</span>
            <div class="config-control">
                <el-date-picker
                    :model-value="component.props.schedule_start || ''"
                    type="datetime"
                    value-format="YYYY-MM-DD HH:mm:ss"
                    placeholder="不限制"
                    clearable
                    style="width: 100%"
                    @update:model-value="(v: string | null) => setProp('schedule_start', v || '')"
                    @focus="$emit('begin')"
                />
            </div>
        </div>
        <div class="config-row">
            <span class="config-label">结束时间</span>
            <div class="config-control">
                <el-date-picker
                    :model-value="component.props.schedule_end || ''"
                    type="datetime"
                    value-format="YYYY-MM-DD HH:mm:ss"
                    placeholder="不限制"
                    clearable
                    style="width: 100%"
                    @update:model-value="(v: string | null) => setProp('schedule_end', v || '')"
                    @focus="$emit('begin')"
                />
                <div class="config-field-hint">定时区间外 C 端不渲染</div>
            </div>
        </div>
        <div class="config-row config-row--top">
            <span class="config-label">备注</span>
            <div class="config-control">
                <el-input
                    :model-value="component.props.admin_note || ''"
                    type="textarea"
                    :rows="2"
                    placeholder="仅编辑器可见"
                    @focus="$emit('begin')"
                    @update:model-value="(v: string) => setProp('admin_note', v)"
                />
            </div>
        </div>
    </div>
    <div v-else class="config-panel">
        <el-empty description="请选择组件" :image-size="60" />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ component: any | null }>()
const emit = defineEmits<{ (e: 'begin'): void }>()

const platforms = computed(() => {
    const p = props.component?.props?.platforms
    return Array.isArray(p) ? p.map(String) : []
})

function onVisible(v: any) {
    if (!props.component) return
    emit('begin')
    props.component.hidden = !v
}

function onPlatforms(v: Array<string | number | boolean>) {
    if (!props.component) return
    emit('begin')
    props.component.props.platforms = v.map(String)
}

function setProp(key: string, value: any) {
    if (!props.component) return
    emit('begin')
    props.component.props[key] = value
}
</script>

<style lang="scss">
@import '../config-ui.scss';
</style>
