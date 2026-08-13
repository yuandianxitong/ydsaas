<template>
    <div class="login-page">
        <!-- 顶部品牌栏 -->
        <header class="login-topbar">
            <div class="topbar-logo">
                <img src="@/assets/images/logo.png" alt="logo" />
            </div>
        </header>

        <!-- 居中卡片 -->
        <div class="login-wrapper">
            <div class="login-card">
                <!-- 左侧：插画区 -->
                <div class="login-illustration">
                    <img src="@/assets/images/login_image.png" :alt="$t('login.title')" />
                </div>

                <!-- 右侧：表单区 -->
                <div class="login-form-panel">
                    <el-form
                        ref="loginFormRef"
                        :model="loginForm"
                        :rules="loginRules"
                        size="large"
                        class="login-form"
                        label-position="top"
                        hide-required-asterisk
                        @submit.prevent="handleLogin"
                    >
                        <el-form-item prop="username">
                            <template #label>
                                <span class="form-label">
                                    <span class="required-mark">*</span>
                                    {{ $t('login.username') }}
                                </span>
                            </template>
                            <el-input
                                v-model="loginForm.username"
                                :placeholder="$t('login.message.username.required')"
                                :disabled="loading"
                            />
                        </el-form-item>

                        <el-form-item prop="password">
                            <template #label>
                                <span class="form-label">
                                    <span class="required-mark">*</span>
                                    {{ $t('login.password') }}
                                </span>
                            </template>
                            <el-input
                                v-model="loginForm.password"
                                type="password"
                                :placeholder="$t('login.message.password.required')"
                                :disabled="loading"
                                show-password
                                @keyup.enter="handleLogin"
                            />
                        </el-form-item>

                        <el-form-item prop="captcha">
                            <template #label>
                                <span class="form-label">
                                    <span class="required-mark">*</span>
                                    {{ $t('login.captchaCode') }}
                                </span>
                            </template>
                            <div class="captcha-row">
                                <el-input
                                    v-model="loginForm.captcha"
                                    :placeholder="$t('login.captchaPlaceholder')"
                                    :disabled="loading"
                                    maxlength="6"
                                    @keyup.enter="handleLogin"
                                />
                                <div
                                    class="captcha-image"
                                    :title="$t('login.captchaRefresh')"
                                    @click="refreshCaptcha"
                                >
                                    <img
                                        v-if="captchaImage"
                                        :src="captchaImage"
                                        :alt="$t('login.captchaAlt')"
                                    />
                                    <div v-else class="captcha-loading">
                                        <el-icon class="is-loading"><Loading /></el-icon>
                                    </div>
                                </div>
                            </div>
                        </el-form-item>

                        <div class="form-options">
                            <el-checkbox v-model="rememberMe">{{
                                $t('login.rememberMe')
                            }}</el-checkbox>
                        </div>

                        <el-button
                            type="primary"
                            size="large"
                            :loading="loading"
                            class="login-btn"
                            @click="handleLogin"
                        >
                            {{ loading ? t('login.captchaLoading') : t('login.login') }}
                        </el-button>
                    </el-form>
                </div>
            </div>

            <div class="login-footer">
                <span>&copy; {{ new Date().getFullYear() }} Dev007.cn</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts" name="Login">
import { Loading } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

import { authApi } from '@/api/auth'
import useUserStore from '@/store/modules/user.store'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const loginFormRef = ref<FormInstance>()

const loginForm = reactive({
    username: '',
    password: '',
    captcha: '',
    captcha_key: ''
})

const rememberMe = ref(true)

const loginRules: FormRules = {
    username: [
        { required: true, message: t('login.message.username.required'), trigger: 'blur' },
        { min: 3, max: 20, message: t('admin.validate.usernameLength'), trigger: 'blur' }
    ],
    password: [
        { required: true, message: t('login.message.password.required'), trigger: 'blur' },
        { min: 6, max: 20, message: t('admin.validate.passwordLength'), trigger: 'blur' }
    ],
    captcha: [{ required: true, message: t('login.message.captchaCode.required'), trigger: 'blur' }]
}

const loading = ref(false)
const captchaImage = ref('')

const refreshCaptcha = async () => {
    try {
        const response = await authApi.getCaptcha()
        captchaImage.value = response.data.image
        loginForm.captcha_key = response.data.key
        loginForm.captcha = ''
    } catch (error) {
        console.error('获取验证码失败:', error)
    }
}

const handleLogin = async () => {
    if (!loginFormRef.value) return

    try {
        await loginFormRef.value.validate()

        loading.value = true

        await userStore.login({
            username: loginForm.username,
            password: loginForm.password,
            captcha: loginForm.captcha,
            captcha_key: loginForm.captcha_key
        })

        const redirect = (route.query.redirect as string) || '/'
        await router.push(redirect)
    } catch (error) {
        refreshCaptcha()
        console.error('登录失败:', error)
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    refreshCaptcha()
})
</script>

<style lang="scss" scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background-color: #f0f2f5;
}

/* ===== 顶部品牌栏 ===== */
.login-topbar {
    height: 64px;
    padding: 0 32px;
    background-color: #fff;
    border-bottom: 1px solid #e8e8e8;
    display: flex;
    align-items: center;

    .topbar-logo img {
        height: 36px;
        width: auto;
    }
}

/* ===== 卡片容器 ===== */
.login-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.login-card {
    display: flex;
    width: 100%;
    max-width: 960px;
    min-height: 580px;
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

/* ===== 左侧插画 ===== */
.login-illustration {
    flex: 1;
    background: linear-gradient(135deg, #4f6cff 0%, #5b6dee 50%, #4458d6 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    position: relative;
    overflow: hidden;

    &::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.08) 0, transparent 40%),
            radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.06) 0, transparent 40%);
    }

    img {
        position: relative;
        max-width: 100%;
        max-height: 420px;
        width: auto;
        height: auto;
        object-fit: contain;
    }
}

/* ===== 右侧表单 ===== */
.login-form-panel {
    flex: 1;
    padding: 60px 56px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-form {
    :deep(.el-form-item) {
        margin-bottom: 24px;
    }

    :deep(.el-form-item__label) {
        padding: 0 0 6px;
        line-height: 1.4;
    }

    :deep(.el-input__wrapper) {
        border-radius: 4px;
        padding: 6px 12px;
        box-shadow: 0 0 0 1px #d9d9d9;
        transition: all 0.2s;

        &:hover {
            box-shadow: 0 0 0 1px #4f6cff;
        }

        &.is-focus {
            box-shadow: 0 0 0 1px #4f6cff;
        }
    }
}

.form-label {
    font-size: 14px;
    color: #333;
    font-weight: 400;

    .required-mark {
        color: #f5222d;
        margin-right: 4px;
    }
}

/* 验证码行 */
.captcha-row {
    display: flex;
    gap: 12px;
    width: 100%;

    .el-input {
        flex: 1;
    }
}

.captcha-image {
    flex-shrink: 0;
    width: 130px;
    height: 40px;
    border-radius: 4px;
    overflow: hidden;
    cursor: pointer;
    border: 1px solid #d9d9d9;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.2s;

    &:hover {
        border-color: #4f6cff;
    }

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
}

.captcha-loading {
    color: #bfbfbf;
}

/* 选项行 */
.form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: -4px 0 20px;
}

/* 登录按钮 */
.login-btn {
    width: 100%;
    height: 44px;
    border-radius: 4px;
    font-size: 15px;
    font-weight: 500;
    background-color: #4f6cff;
    border-color: #4f6cff;

    &:hover,
    &:focus {
        background-color: #6478ff;
        border-color: #6478ff;
    }
}

.login-footer {
    margin-top: 24px;
    font-size: 12px;
    color: #999;
}

/* ===== 响应式 ===== */
@media (max-width: 860px) {
    .login-card {
        flex-direction: column;
        max-width: 460px;
        min-height: auto;
    }

    .login-illustration {
        min-height: 220px;
        padding: 24px;

        img {
            max-height: 180px;
        }
    }

    .login-form-panel {
        padding: 40px 32px;
    }
}

@media (max-width: 480px) {
    .login-topbar {
        padding: 0 16px;
    }

    .login-form-panel {
        padding: 32px 24px;
    }
}
</style>
