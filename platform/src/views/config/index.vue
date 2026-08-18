<template>
    <div class="platform-config">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('system.config.title') }}</div>
                <div class="page-desc">{{ $t('system.config.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button @click="handleReset">{{ $t('config.resetConfig') }}</el-button>
                <el-button type="primary" :loading="loading" @click="handleSave">
                    {{ $t('config.saveConfig') }}
                </el-button>
            </div>
        </div>

        <div class="set-split">
            <div class="set-nav">
                <div
                    v-for="(label, key) in configGroups"
                    :key="key"
                    class="set-nav-item"
                    :class="{ on: activeTab === key }"
                    @click="switchTab(String(key))"
                >
                    <i class="ic" :class="navIconClass[String(key)] || 'i-svg:settings'" />
                    <span>{{ label }}</span>
                </div>
            </div>

            <div class="set-sections">
                <div class="set-card">
                    <div class="set-card-head">
                        <h3>{{ configGroups[activeTab] || activeTab }}</h3>
                    </div>
                    <div class="set-card-body">
                        <template v-for="config in visibleConfigs" :key="config.id">
                            <div class="set-item">
                                <div>
                                    <div class="set-item-label">{{ config.config_name }}</div>
                                    <div
                                        v-if="config.config_desc && config.config_type !== 'file'"
                                        class="set-item-desc"
                                    >
                                        {{ config.config_desc }}
                                    </div>
                                </div>
                                <div class="set-item-ctrl">
                                    <template v-if="config.config_type === 'select'">
                                        <el-select
                                            v-model="formData[config.config_key]"
                                            :placeholder="$t('common.selectPlaceholder')"
                                            style="width: 320px"
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

                                    <template v-else-if="config.config_type === 'boolean'">
                                        <el-switch
                                            v-model="formData[config.config_key]"
                                            :active-value="1"
                                            :inactive-value="0"
                                        />
                                    </template>

                                    <template v-else-if="config.config_type === 'number'">
                                        <el-input-number
                                            v-model="formData[config.config_key]"
                                            :min="0"
                                            style="width: 200px"
                                        />
                                    </template>

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
                                                <i v-else class="i-svg:plus image-uploader-icon" />
                                            </el-upload>
                                            <div class="upload-tip">
                                                {{ config.config_desc }}
                                            </div>
                                        </div>
                                    </template>

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
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts" name="PlatformConfig">
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformConfigApi } from '@/api/system'
import useAppStore from '@/store/modules/app.store'
import { getToken } from '@/utils/auth'

const { t } = useI18n()

const navIconClass: Record<string, string> = {
    basic: 'i-svg:settings',
    email: 'i-svg:mail',
    sms: 'i-svg:smartphone',
    storage: 'i-svg:server',
    payment: 'i-svg:wallet',
    wechat_official: 'i-svg:bot',
    wechat_open: 'i-svg:globe',
    wechat_mini: 'i-svg:message-square'
}

const activeTab = ref('basic')
const loading = ref(false)
const appStore = useAppStore()

const configGroups = ref<Record<string, string>>({})
const configsData = reactive<Record<string, any[]>>({})
const formData = reactive<Record<string, any>>({})
const originalFormData = reactive<Record<string, any>>({})

const uploadHeaders = computed(() => ({
    Authorization: `Bearer ${getToken()}`
}))

const isSecretKey = (key: string): boolean => {
    return (
        key.includes('password') ||
        key.includes('secret') ||
        key.includes('private_key') ||
        key.includes('api_key')
    )
}

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

const switchTab = async (key: string) => {
    activeTab.value = key
    if (!configsData[key]) {
        await loadConfigs(key)
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
}
</style>
