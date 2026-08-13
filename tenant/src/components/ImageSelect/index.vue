<template>
    <div class="image-select">
        <!-- 单图 -->
        <template v-if="!multiple">
            <div class="image-select-box" @click="openPicker">
                <template v-if="modelValue">
                    <img :src="modelValue as string" class="preview-img" alt="" />
                    <div class="remove-btn" @click.stop="handleRemove(0)">
                        <el-icon><Close /></el-icon>
                    </div>
                </template>
                <el-icon v-else class="add-icon"><Plus /></el-icon>
            </div>
        </template>

        <!-- 多图 -->
        <template v-else>
            <div class="image-select-multi">
                <div
                    v-for="(url, idx) in (modelValue as string[])"
                    :key="url"
                    class="image-select-box is-item"
                >
                    <img :src="url" class="preview-img" alt="" />
                    <div class="remove-btn" @click.stop="handleRemove(idx)">
                        <el-icon><Close /></el-icon>
                    </div>
                </div>
                <div
                    v-if="!limit || (modelValue as string[]).length < limit"
                    class="image-select-box"
                    @click="openPicker"
                >
                    <el-icon class="add-icon"><Plus /></el-icon>
                </div>
            </div>
        </template>

        <MaterialPicker
            v-model="pickerVisible"
            :multiple="multiple"
            :limit="remainLimit"
            @confirm="onPicked"
        />
    </div>
</template>

<script setup lang="ts">
import { Close, Plus } from '@element-plus/icons-vue'
import { computed, ref } from 'vue'

import MaterialPicker from '@/components/MaterialPicker/index.vue'

interface Props {
    modelValue: string | string[]
    multiple?: boolean
    limit?: number
}

const props = withDefaults(defineProps<Props>(), {
    multiple: false,
    limit: 0
})
const emit = defineEmits<{ (e: 'update:modelValue', value: string | string[]): void }>()

const pickerVisible = ref(false)

// 多图时把剩余可选数传给素材库，避免超选
const remainLimit = computed(() => {
    if (!props.multiple || !props.limit) return props.limit ?? 0
    const current = Array.isArray(props.modelValue) ? props.modelValue.length : 0
    return Math.max(props.limit - current, 0)
})

function openPicker() {
    pickerVisible.value = true
}

function onPicked(urls: string[]) {
    if (!urls.length) return
    if (!props.multiple) {
        emit('update:modelValue', urls[0])
        return
    }
    const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
    const merged = [...current, ...urls]
    emit('update:modelValue', props.limit ? merged.slice(0, props.limit) : merged)
}

function handleRemove(idx: number) {
    if (!props.multiple) {
        emit('update:modelValue', '')
    } else {
        const arr = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        arr.splice(idx, 1)
        emit('update:modelValue', arr)
    }
}
</script>

<style scoped lang="scss">
.image-select-box {
  width: 80px;
  height: 80px;
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-lg);
  cursor: pointer;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: border-color 0.2s;

  &:hover {
    border-color: var(--el-color-primary);
  }

  &.is-item {
    border-style: solid;
  }

  .preview-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .remove-btn {
    position: absolute;
    top: 0;
    right: 0;
    width: 20px;
    height: 20px;
    background: rgba(0, 0, 0, 0.5);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-bottom-left-radius: var(--radius-md);
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.15s;
  }

  &:hover .remove-btn {
    opacity: 1;
  }

  .add-icon {
    font-size: 26px;
    color: var(--color-text-tertiary);
  }
}

.image-select-multi {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
