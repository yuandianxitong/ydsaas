<template>
  <div class="decor-page">
    <DecorPageHeader title="启动与首页" subtitle="设置 App 冷启动落地页；与底部导航相互独立">
      <template #actions>
        <el-button @click="loadAll">重置</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </DecorPageHeader>

    <DecorSection title="启动入口（可选）">
      <div class="decor-hint" style="margin-bottom: 14px">
        留空则使用「首页装修」内容作为 App 启动落地页；仅当想用内置「首页」或某插件应用自带首页作为入口时才选择。
        保存后用户刷新或重新进入即可生效，无需重新打包。
      </div>
      <el-form label-position="top" class="decor-form">
        <el-form-item label="首页应用">
          <el-select
            v-model="form.home_app_code"
            placeholder="选择"
            clearable
            style="width: 100%; max-width: 420px"
            @change="onHomeAppChange"
          >
            <el-option
              v-for="opt in homeOptions"
              :key="opt.code"
              :label="`${opt.name} (${opt.code})`"
              :value="opt.code"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="首页路径">
          <el-input
            v-model="form.home_page"
            placeholder="modules/mall/pages/home/index"
            style="max-width: 420px"
          />
          <div class="decor-field-tip">选择首页应用后会自动填充，可手动改写为该应用下的其它页面。</div>
        </el-form-item>
      </el-form>
    </DecorSection>
  </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import { mobileConfigApi, type EligiblePluginEntry } from '@/api/mobile-config'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const form = reactive({
  home_app_code: '',
  home_page: '',
})

const homeOptions = ref<EligiblePluginEntry[]>([])
const saving = ref(false)

async function loadAll() {
  const [cfg, eligible] = await Promise.all([mobileConfigApi.get(), mobileConfigApi.eligible()])
  form.home_app_code = cfg.data.home_app_code || ''
  form.home_page = cfg.data.home_page || ''
  homeOptions.value = eligible.data.homeOptions || []
}

function onHomeAppChange(code: string) {
  const opt = homeOptions.value.find((x) => x.code === code)
  if (opt) form.home_page = opt.default_home_path
  if (!code) form.home_page = ''
}

async function handleSave() {
  saving.value = true
  try {
    const res = await mobileConfigApi.update({
      home_app_code: form.home_app_code,
      home_page: form.home_page,
    })
    form.home_app_code = res.data.home_app_code || ''
    form.home_page = res.data.home_page || ''
    ElMessage.success('已保存，用户刷新或重新进入即可看到')
  } finally {
    saving.value = false
  }
}

onMounted(loadAll)
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.decor-field-tip {
  margin-top: 4px;
  font-size: 11.5px;
  color: var(--color-text-tertiary);
  line-height: 1.5;
}
</style>
