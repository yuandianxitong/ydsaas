<template>
  <main class="default-home">
    <section class="hero">
      <p class="eyebrow">YdAdmin SaaS</p>
      <h1>{{ siteStore.title }}</h1>
      <p class="lead">
        {{ siteStore.config.site_intro || '多租户、插件化、内容管理、移动端构建与 PC 前台能力一体化的 SaaS 基础平台。' }}
      </p>
      <div class="actions">
        <NuxtLink v-if="siteStore.config.login_enabled" to="/login" class="primary">会员登录</NuxtLink>
        <NuxtLink to="/p/home" class="secondary">查看单页</NuxtLink>
      </div>
    </section>

    <section class="features">
      <article v-for="item in features" :key="item.title">
        <span>{{ item.index }}</span>
        <h2>{{ item.title }}</h2>
        <p>{{ item.desc }}</p>
      </article>
    </section>
  </main>
</template>

<script setup lang="ts">
import { useSiteStore } from '~/store/site'

definePageMeta({ middleware: 'home' })

const siteStore = useSiteStore()
const features = [
  { index: '01', title: '多租户隔离', desc: '基于子域名解析租户上下文，Repository 自动注入 tenant scope。' },
  { index: '02', title: '应用插件体系', desc: '插件可提供后台菜单、C 端 API、移动端分包和 PC 前台页面。' },
  { index: '03', title: 'CMS 官网能力', desc: '启用 CMS 应用后，可把 CMS 声明的 PC 首页作为租户官网入口。' },
  { index: '04', title: '会员前台', desc: 'PC、H5、UniApp 共用租户会员登录、余额、积分和业务接口。' },
  { index: '05', title: '装修与发布', desc: '支持默认单页、移动端配置、打包构建和插件装修组件。' },
  { index: '06', title: '套餐与权益', desc: '套餐、插件购买、授权过期和 C 端权益校验贯穿前后台。' },
]
</script>

<style scoped>
.default-home {
  min-height: calc(100vh - 56px);
  background: #f8fafc;
  color: #0f172a;
}

.hero {
  max-width: 1120px;
  margin: 0 auto;
  padding: 88px 24px 48px;
}

.eyebrow {
  margin: 0 0 14px;
  color: var(--color-primary);
  font-size: 13px;
  font-weight: 800;
  text-transform: uppercase;
}

h1 {
  margin: 0;
  max-width: 760px;
  font-size: 52px;
  line-height: 1.08;
  font-weight: 900;
}

.lead {
  max-width: 680px;
  margin: 22px 0 0;
  color: #475569;
  font-size: 17px;
  line-height: 1.9;
}

.actions {
  display: flex;
  gap: 12px;
  margin-top: 30px;
}

.actions a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 42px;
  padding: 0 18px;
  text-decoration: none;
  border: 1px solid #cbd5e1;
}

.primary {
  color: #fff;
  background: var(--color-primary);
  border-color: var(--color-primary) !important;
}

.secondary {
  color: #0f172a;
  background: #fff;
}

.features {
  max-width: 1120px;
  margin: 0 auto;
  padding: 0 24px 88px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

article {
  background: #fff;
  border: 1px solid #e2e8f0;
  padding: 22px;
}

article span {
  color: var(--color-primary);
  font-weight: 800;
  font-size: 12px;
}

article h2 {
  margin: 12px 0 8px;
  font-size: 18px;
}

article p {
  margin: 0;
  color: #64748b;
  line-height: 1.75;
}

@media (max-width: 900px) {
  h1 { font-size: 36px; }
  .features { grid-template-columns: 1fr; }
  .actions { flex-wrap: wrap; }
}
</style>
