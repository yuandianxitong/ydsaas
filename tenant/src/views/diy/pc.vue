<template>
  <div class="decor-page">
    <DecorPageHeader title="PC端配置" subtitle="配置租户 PC 前台的站点信息、首页与导航">
      <template #actions>
        <el-button @click="load">重置</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </DecorPageHeader>

    <DecorSection title="站点信息">
      <el-form label-position="top" class="decor-form pc-config-form">
        <el-form-item label="站点名称">
          <el-input v-model="form.site_name" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="站点 Logo">
          <ImageSelect :model-value="form.site_logo" @update:model-value="(v: string | string[]) => (form.site_logo = v as string)" />
        </el-form-item>
        <el-form-item label="站点简介">
          <el-input v-model="form.site_intro" type="textarea" :rows="2" maxlength="255" />
        </el-form-item>
        <el-form-item label="主题色">
          <el-color-picker v-model="form.theme_color" />
        </el-form-item>
      </el-form>
    </DecorSection>

    <DecorSection title="首页">
      <el-form label-position="top" class="decor-form pc-config-form">
        <el-form-item label="首页类型">
          <el-radio-group v-model="form.home_type" @change="handleHomeTypeChange">
            <el-radio value="diy">默认单页</el-radio>
            <el-radio value="app">应用首页</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="form.home_type !== 'diy'" label="首页页面">
          <el-select v-model="homeSelection" filterable placeholder="请选择插件页面" @change="applyHomeSelection">
            <el-option-group
              v-for="plugin in options.homeOptions"
              :key="plugin.code"
              :label="plugin.name"
            >
              <el-option
                v-for="page in plugin.pages"
                :key="`${plugin.code}:${page.route}`"
                :label="`${page.title} ${page.route}`"
                :value="`${plugin.code}|${page.route}`"
              />
            </el-option-group>
          </el-select>
        </el-form-item>
      </el-form>
    </DecorSection>

    <DecorSection title="导航">
      <div class="nav-toolbar">
        <el-select v-model="navSelection" filterable placeholder="从插件页面添加导航" @change="addNavFromSelection">
          <el-option-group v-for="plugin in options.navOptions" :key="plugin.code" :label="plugin.name">
            <el-option
              v-for="page in plugin.pages"
              :key="`${plugin.code}:${page.route}`"
              :label="`${page.title} ${page.route}`"
              :value="`${plugin.code}|${page.route}|${page.title}|${page.auth ? 1 : 0}`"
            />
          </el-option-group>
        </el-select>
        <el-button @click="addCustomNav">添加自定义导航</el-button>
      </div>

      <el-table :data="form.nav" border class="nav-table">
        <el-table-column label="名称" min-width="160">
          <template #default="{ row }">
            <el-input v-model="row.label" />
          </template>
        </el-table-column>
        <el-table-column label="路径" min-width="220">
          <template #default="{ row }">
            <el-input v-model="row.path" />
          </template>
        </el-table-column>
        <el-table-column label="需要登录" width="120">
          <template #default="{ row }">
            <el-switch v-model="row.auth" />
          </template>
        </el-table-column>
        <el-table-column label="排序" width="110">
          <template #default="{ row, $index }">
            <el-input-number v-model="row.sort" :min="1" :max="99" :controls="false" @change="sortNav" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="90">
          <template #default="{ $index }">
            <el-button link type="danger" @click="form.nav.splice($index, 1)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </DecorSection>

    <DecorSection title="访问与 SEO">
      <el-form label-position="top" class="decor-form pc-config-form">
        <el-form-item label="登录注册">
          <div class="switch-row">
            <el-switch v-model="form.login_enabled" active-text="显示登录入口" />
            <el-switch v-model="form.register_enabled" active-text="显示注册入口" />
          </div>
        </el-form-item>
        <el-form-item label="SEO 标题">
          <el-input v-model="form.seo.title" maxlength="120" />
        </el-form-item>
        <el-form-item label="SEO 关键词">
          <el-input v-model="form.seo.keywords" maxlength="200" />
        </el-form-item>
        <el-form-item label="SEO 描述">
          <el-input v-model="form.seo.description" type="textarea" :rows="2" maxlength="255" />
        </el-form-item>
      </el-form>
    </DecorSection>
  </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'

import { pcConfigApi, type PcConfig, type PcConfigOptions, type PcNavItem } from '@/api/pc-config'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'

const emptyOptions: PcConfigOptions = { homeOptions: [], navOptions: [], fallback: { type: 'diy', page_key: 'home' } }
const options = reactive<PcConfigOptions>({ ...emptyOptions })
const form = reactive<PcConfig>({
  site_name: '',
  site_logo: '',
  site_intro: '',
  theme_color: '#2563eb',
  home_type: 'diy',
  home_app_code: '',
  home_page: 'home',
  nav: [],
  seo: {},
  login_enabled: true,
  register_enabled: true,
  status: 1,
})

const saving = ref(false)
const homeSelection = ref('')
const navSelection = ref('')

function fillConfig(data: PcConfig) {
  Object.assign(form, {
    ...data,
    nav: Array.isArray(data.nav) ? data.nav : [],
    seo: data.seo || {},
    login_enabled: data.login_enabled !== false,
    register_enabled: data.register_enabled !== false,
  })
  homeSelection.value = form.home_app_code && form.home_page ? `${form.home_app_code}|${form.home_page}` : ''
}

async function load() {
  const [configRes, optionRes] = await Promise.all([pcConfigApi.get(), pcConfigApi.options()])
  fillConfig(configRes.data)
  Object.assign(options, optionRes.data || emptyOptions)
}

function handleHomeTypeChange() {
  if (form.home_type === 'diy') {
    form.home_app_code = ''
    form.home_page = 'home'
    homeSelection.value = ''
  }
}

function applyHomeSelection(value: string) {
  const [code, route] = value.split('|')
  form.home_app_code = code || ''
  form.home_page = route || ''
}

function addNavFromSelection(value: string) {
  const [code, path, title, auth] = value.split('|')
  if (!code || !path) return
  if (form.nav.some((item) => item.code === code && item.path === path)) {
    ElMessage.warning('该导航已存在')
    navSelection.value = ''
    return
  }
  form.nav.push({ label: title || path, path, code, auth: auth === '1', sort: form.nav.length + 1 })
  navSelection.value = ''
  sortNav()
}

function addCustomNav() {
  form.nav.push({ label: '新导航', path: '/', code: '', auth: false, sort: form.nav.length + 1 })
}

function sortNav() {
  form.nav.sort((a: PcNavItem, b: PcNavItem) => Number(a.sort || 0) - Number(b.sort || 0))
}

async function handleSave() {
  saving.value = true
  try {
    sortNav()
    const res = await pcConfigApi.update({ ...form })
    fillConfig(res.data)
    ElMessage.success('已保存')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.pc-config-form {
  max-width: 560px;
}

.nav-toolbar {
  display: flex;
  gap: 12px;
  max-width: 680px;
  margin-bottom: 14px;
}

.nav-toolbar .el-select {
  flex: 1;
}

.nav-table {
  max-width: 920px;
}

.switch-row {
  display: flex;
  gap: 24px;
  align-items: center;
}
</style>
