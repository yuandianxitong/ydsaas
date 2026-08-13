<template>
    <div class="platform-config">
        <el-card class="box-card">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <el-tab-pane
                    v-for="(label, key) in configGroups"
                    :key="key"
                    :label="label"
                    :name="key"
                >
                    <div class="config-form">
                        <el-form
                            ref="formRef"
                            :model="formData"
                            label-width="160px"
                            label-position="left"
                        >
                            <template v-for="config in visibleConfigs" :key="config.id">
                                <el-form-item :label="config.config_name" :prop="config.config_key">
                                    <!-- select 下拉选择 -->
                                    <template v-if="config.config_type === 'select'">
                                        <el-select
                                            v-model="formData[config.config_key]"
                                            :placeholder="$t('common.selectPlaceholder')"
                                            style="width: 400px"
                                        >
                                            <el-option
                                                v-for="(optLabel, optValue) in parseOptions(
                                                    config.config_options
                                                )"
                                                :key="optValue"
                                                :label="String(optLabel)"
                                                :value="optValue"
                                            />
                                        </el-select>
                                    </template>

                                    <!-- boolean 开关 -->
                                    <template v-else-if="config.config_type === 'boolean'">
                                        <el-switch
                                            v-model="formData[config.config_key]"
                                            :active-value="1"
                                            :inactive-value="0"
                                        />
                                    </template>

                                    <!-- number 数字 -->
                                    <template v-else-if="config.config_type === 'number'">
                                        <el-input-number
                                            v-model="formData[config.config_key]"
                                            :min="0"
                                            style="width: 200px"
                                        />
                                    </template>

                                    <!-- file 图片上传 -->
                                    <template v-else-if="config.config_type === 'file'">
                                        <div>
                                            <el-upload
                                                class="image-uploader"
                                                :show-file-list="false"
                                                :on-success="
                                                    (response: any) =>
                                                        handleUploadSuccess(
                                                            response,
                                                            config.config_key
                                                        )
                                                "
                                                :before-upload="beforeUpload"
                                                action="/platformapi/upload/image"
                                                :headers="uploadHeaders"
                                            >
                                                <img
                                                    v-if="formData[config.config_key]"
                                                    :src="getImageUrl(formData[config.config_key])"
                                                    class="uploaded-image"
                                                    :alt="config.config_name"
                                                />
                                                <el-icon v-else class="image-uploader-icon">
                                                    <Plus />
                                                </el-icon>
                                            </el-upload>
                                            <div class="upload-tip">
                                                {{ config.config_desc }}
                                            </div>
                                        </div>
                                    </template>

                                    <!-- string 默认文本 -->
                                    <template v-else>
                                        <el-input
                                            v-model="formData[config.config_key]"
                                            :type="
                                                isSecretKey(config.config_key) ? 'password' : 'text'
                                            "
                                            :show-password="isSecretKey(config.config_key)"
                                            :placeholder="config.config_desc"
                                            style="width: 400px"
                                        />
                                    </template>

                                    <div
                                        v-if="config.config_desc && config.config_type !== 'file'"
                                        class="config-desc"
                                    >
                                        {{ config.config_desc }}
                                    </div>
                                </el-form-item>
                            </template>
                        </el-form>

                        <div class="form-actions">
                            <el-button type="primary" :loading="loading" @click="handleSave">
                                {{ $t('config.saveConfig') }}
                            </el-button>
                            <el-button @click="handleReset">{{
                                $t('config.resetConfig')
                            }}</el-button>
                        </div>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup lang="ts" name="PlatformConfig">
import { Plus } from '@element-plus/icons-vue'
import type { FormInstance } from 'element-plus'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformConfigApi } from '@/api/system'
import useAppStore from '@/store/modules/app.store'
import { getToken } from '@/utils/auth'

const { t } = useI18n()

const activeTab = ref('basic')
const loading = ref(false)
const formRef = ref<FormInstance>()
const appStore = useAppStore()

const configGroups = ref<Record<string, string>>({})
const configsData = reactive<Record<string, any[]>>({})
const formData = reactive<Record<string, any>>({})
const originalFormData = reactive<Record<string, any>>({})

// 上传请求头（computed 确保 Token 刷新后仍有效）
const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${getToken()}`
}))

/**
 * 判断是否为敏感字段（密码/密钥类）
 */
const isSecretKey = (key: string): boolean => {
    return (
        key.includes('password') ||
        key.includes('secret') ||
        key.includes('private_key') ||
        key.includes('api_key')
    )
}

/**
 * 解析 config_options JSON 为对象
 */
const parseOptions = (options: any): Record<string, string> => {
    if (!options) return {}
    if (typeof options === 'string') {
        try {
            return JSON.parse(options)
        } catch {
            return {}
        }
    }
    return options
}

/**
 * 判断配置项是否满足 depends 条件
 */
const checkDepends = (config: any): boolean => {
    if (!config.config_depends) return true

    let depends = config.config_depends
    if (typeof depends === 'string') {
        try {
            depends = JSON.parse(depends)
        } catch {
            return true
        }
    }

    const field = depends.field
    const expectedValue = depends.value
    const currentValue = String(formData[field] ?? '')

    if (Array.isArray(expectedValue)) {
        return expectedValue.map(String).includes(currentValue)
    }
    return currentValue === String(expectedValue)
}

const currentConfigs = computed(() => {
    return configsData[activeTab.value] || []
})

const visibleConfigs = computed(() => {
    return currentConfigs.value.filter(checkDepends)
})

// 获取图片完整 URL
const getImageUrl = (url: string) => {
    return appStore.getImageUrl(url)
}

onMounted(async () => {
    await loadConfigGroups()
    await loadConfigs(activeTab.value)
})

const loadConfigGroups = async () => {
    try {
        const res = await platformConfigApi.groups()
        configGroups.value = res.data || {}
    } catch (error) {
        console.error('Failed to load config groups', error)
    }
}

const loadConfigs = async (group: string) => {
    try {
        loading.value = true
        const res = await platformConfigApi.list(group)
        const configs = res.data || []

        configsData[group] = configs

        const newFormData: Record<string, any> = {}
        const newOriginalFormData: Record<string, any> = {}

        configs.forEach((config: any) => {
            let value = config.config_value

            if (config.config_type === 'boolean') {
                value = Number(value)
            } else if (config.config_type === 'number') {
                value = Number(value)
            } else if (config.config_type === 'json') {
                try {
                    value = JSON.parse(value)
                } catch {
                    value = {}
                }
            }

            newFormData[config.config_key] = value
            newOriginalFormData[config.config_key] = value
        })

        Object.assign(formData, newFormData)
        Object.assign(originalFormData, newOriginalFormData)
    } catch (error) {
        console.error('Failed to load configs', error)
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const handleTabChange = async (tabName: string | number) => {
    const group = String(tabName)
    if (!configsData[group]) {
        await loadConfigs(group)
    }
}

const handleUploadSuccess = (response: Record<string, any>, configKey: string) => {
    if (response.code === 200 || response.code === 0) {
        formData[configKey] = response.data?.url || response.data?.path || response.data
        ElMessage.success(t('message.uploadSuccess'))
    } else {
        ElMessage.error(response.message || t('message.uploadFailed'))
    }
}

const beforeUpload = (file: File) => {
    const isImage = file.type.startsWith('image/')
    if (!isImage) {
        ElMessage.error(t('message.imageOnly'))
        return false
    }

    const isValidSize = file.size / 1024 / 1024 < 2
    if (!isValidSize) {
        ElMessage.error(t('message.fileSizeLimit'))
        return false
    }

    return true
}

const handleSave = async () => {
    try {
        loading.value = true

        const updateConfigs = currentConfigs.value.map((config) => {
            let value = formData[config.config_key]

            if (config.config_type === 'json') {
                value = JSON.stringify(value)
            } else {
                value = String(value)
            }

            return {
                config_key: config.config_key,
                config_value: value
            }
        })

        await platformConfigApi.batchUpdate(updateConfigs)

        Object.assign(originalFormData, formData)

        ElMessage.success(t('message.configSaveSuccess'))
    } catch (error) {
        console.error('Failed to save configs', error)
        if (error instanceof Error) {
            ElMessage.error(t('message.configSaveFailed') + ': ' + error.message)
        } else {
            ElMessage.error(t('message.configSaveFailed'))
        }
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    ElMessageBox.confirm(t('message.configResetConfirm'), t('common.tips'), {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        type: 'warning'
    }).then(() => {
        Object.assign(formData, originalFormData)
        ElMessage.success(t('message.configResetDone'))
    })
}
</script>

<style scoped lang="scss">
.platform-config {
    .config-form {
        margin-top: 20px;

        .config-desc {
            font-size: 12px;
            color: var(--color-text-tertiary);
            margin-top: 4px;
            margin-left: 10px;
        }

        .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--color-divider);
            text-align: center;
        }
    }

    // 图片上传器样式
    .image-uploader {
        :deep(.el-upload) {
            border: 1px dashed var(--color-border);
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;

            &:hover {
                border-color: var(--el-color-primary);
            }
        }

        .uploaded-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .image-uploader-icon {
            font-size: 28px;
            color: var(--color-text-disabled);
        }
    }

    .upload-tip {
        font-size: 12px;
        color: var(--color-text-tertiary);
        margin-top: 8px;
        line-height: 1.4;
    }

    :deep(.el-tabs__content) {
        padding-top: 0;
    }

    :deep(.el-form-item__label) {
        font-weight: 500;
    }
}
</style>
