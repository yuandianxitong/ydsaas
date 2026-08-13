<template>
  <component :is="resolvedComponent" v-if="resolvedComponent" />
  <div v-else class="plugin-page-missing">
    <h1>页面不存在</h1>
    <p>当前租户未配置该 PC 前台页面，或插件页面尚未构建。</p>
  </div>
</template>

<script setup lang="ts">
import { markRaw, shallowRef } from 'vue'
import { pluginRoutes } from '~/generated/plugin-routes'
import { getToken } from '~/composables/useRequest'

const route = useRoute()
const modules = import.meta.glob('../plugins/**/*.vue')
const resolvedComponent = shallowRef<any>(null)

function matchRoute(pattern: string, path: string) {
  const patternParts = pattern.split('/').filter(Boolean)
  const pathParts = path.split('/').filter(Boolean)
  if (patternParts.length !== pathParts.length) return false
  return patternParts.every((part, index) => part.startsWith(':') || part === pathParts[index])
}

const pluginRoute = computed(() => pluginRoutes.find((item) => matchRoute(item.route, route.path)))

async function resolvePluginComponent() {
  resolvedComponent.value = null
  if (!pluginRoute.value) return
  const componentPath = pluginRoute.value.component.endsWith('.vue')
    ? pluginRoute.value.component
    : `${pluginRoute.value.component}.vue`
  const key = `../${componentPath}`
  const loader = modules[key]
  if (!loader) return
  const mod = await loader() as { default?: any }
  resolvedComponent.value = markRaw(mod.default || mod)
}

watch(() => route.path, resolvePluginComponent, { immediate: true })

onMounted(() => {
  if (pluginRoute.value?.auth && !getToken()) {
    navigateTo({ path: '/login', query: { redirect: route.fullPath } })
  }
})
</script>

<style scoped>
.plugin-page-missing {
  max-width: 720px;
  margin: 0 auto;
  padding: 96px 24px;
  text-align: center;
  color: #64748b;
}

.plugin-page-missing h1 {
  margin: 0 0 12px;
  color: #0f172a;
  font-size: 34px;
}
</style>
