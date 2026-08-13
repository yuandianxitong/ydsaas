import { useSiteStore } from '~/store/site'

export default defineNuxtRouteMiddleware(async () => {
  const siteStore = useSiteStore()
  const config = await siteStore.load()

  if (config.home_type === 'app' && config.home_page && config.home_page !== '/') {
    return navigateTo(config.home_page, { replace: true })
  }
})
