<template>
  <div class="decor-page">
    <DecorPageHeader title="基础设置" subtitle="配置移动端店铺的应用信息、客服与分享">
      <template #actions>
        <el-button @click="load">重置</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </DecorPageHeader>

    <DecorSection title="应用信息">
      <el-form label-position="top" class="decor-form" style="max-width: 480px">
        <el-form-item label="应用名称">
          <el-input v-model="form.app_name" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="应用 Logo">
          <ImageSelect :model-value="form.app_logo" @update:model-value="(v: string | string[]) => (form.app_logo = v as string)" />
        </el-form-item>
        <el-form-item label="应用简介">
          <el-input v-model="form.app_intro" type="textarea" :rows="2" maxlength="255" />
        </el-form-item>
      </el-form>
    </DecorSection>

    <DecorSection title="客服">
      <el-form label-position="top" class="decor-form" style="max-width: 480px">
        <el-form-item label="客服方式">
          <el-radio-group v-model="form.service_type">
            <el-radio value="">不显示</el-radio>
            <el-radio value="phone">电话客服</el-radio>
            <el-radio value="wechat">微信客服</el-radio>
            <el-radio value="online">在线客服</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="form.service_type === 'phone'" label="客服电话">
          <el-input v-model="form.service_phone" placeholder="400-826-1688" style="max-width: 240px" />
        </el-form-item>
      </el-form>
    </DecorSection>

    <DecorSection title="分享">
      <el-form label-position="top" class="decor-form" style="max-width: 480px">
        <el-form-item label="默认分享标题">
          <el-input v-model="form.share_title" maxlength="200" />
        </el-form-item>
        <el-form-item label="默认分享图">
          <ImageSelect :model-value="form.share_image" @update:model-value="(v: string | string[]) => (form.share_image = v as string)" />
        </el-form-item>
      </el-form>
    </DecorSection>
  </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import { mobileConfigApi } from '@/api/mobile-config'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const form = reactive({
  app_name: '',
  app_logo: '',
  app_intro: '',
  service_type: '' as '' | 'online' | 'wechat' | 'phone',
  service_phone: '',
  share_title: '',
  share_image: '',
})

const saving = ref(false)

async function load() {
  const res = await mobileConfigApi.get()
  form.app_name = res.data.app_name
  form.app_logo = res.data.app_logo
  form.app_intro = res.data.app_intro || ''
  form.service_type = (res.data.service_type as typeof form.service_type) || ''
  form.service_phone = res.data.service_phone || ''
  form.share_title = res.data.share_title || ''
  form.share_image = res.data.share_image || ''
}

async function handleSave() {
  saving.value = true
  try {
    await mobileConfigApi.update({ ...form })
    ElMessage.success('已保存')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped lang="scss">
@import './components/decor.scss';
</style>
