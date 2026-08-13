import { useSiteStore } from '~/store/site'

/**
 * 登录/注册入口开关的路由级守卫。
 * AppHeader 只做入口显隐；直接输 URL 也必须被拦（login_enabled / register_enabled）。
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const siteStore = useSiteStore()
  const config = await siteStore.load()

  const disabled =
    (to.path === '/login' && !config.login_enabled) ||
    (to.path === '/register' && !config.register_enabled)

  if (disabled) {
    return navigateTo('/', { replace: true })
  }
})
