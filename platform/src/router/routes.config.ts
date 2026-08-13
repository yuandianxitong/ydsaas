// src/router/routes.config.ts
import type { RouteRecordRaw } from 'vue-router'

import { PageEnum } from '@/constants/page'

// 推荐直接懒加载 Layout 组件（更符合 Vite 的分包习惯）
export const LAYOUT = () => import('@/layout/index.vue')

// 首页、动态路由挂载的占位名称（父路由 name）
export const INDEX_ROUTE_NAME = 'INDEX_ROUTE'

// 404 独立导出，方便在需要时复用（可选）
export const NOT_FOUND_ROUTE: RouteRecordRaw = {
    path: '/:pathMatch(.*)*',
    component: () => import('@/views/error/404.vue'),
    meta: { hidden: true }
}

// 常量路由：无需权限或基础错误页、登录页等
export const constantRoutes: Array<RouteRecordRaw> = [
    NOT_FOUND_ROUTE,
    {
        path: PageEnum.ERROR_403,
        component: () => import('@/views/error/403.vue'),
        meta: { hidden: true }
    },
    {
        path: PageEnum.ERROR_500,
        component: () => import('@/views/error/500.vue'),
        meta: { hidden: true }
    },
    {
        path: PageEnum.LOGIN,
        component: () => import('@/views/login/index.vue'),
        meta: { hidden: true }
    },
    {
        // 路径相对于 router base(/platform/)，不能再带 /platform 前缀，否则匹配不到 → 404
        path: PageEnum.MARKETPLACE_OAUTH_CALLBACK,
        component: () => import('@/views/marketplace/oauth-callback.vue'),
        meta: { hidden: true }
    }
]

// "动态挂载"父路由：登录后把后端返回的子路由都挂在这里
// redirect 由 permission.guard 动态设置为第一个有效菜单页，此处不能设为 '/'（会自引用死循环）
export const INDEX_ROUTE: RouteRecordRaw = {
    path: PageEnum.INDEX,
    component: LAYOUT,
    name: INDEX_ROUTE_NAME
}

// 平台 dashboard 占位路由
// M2B 阶段后端菜单为空，permission.guard 会把它当作 INDEX_ROUTE 的 fallback 子路由挂载，
// 保证登录后即使没有任何后端菜单也能落到 /dashboard 而不是空白页。
// M2C 后端菜单填充后可考虑移除或保留为默认落地页。
export const DASHBOARD_ROUTE: RouteRecordRaw = {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/dashboard/index.vue'),
    meta: {
        title: '首页',
        icon: 'HomeFilled',
        keepAlive: true,
        hidden: false,
        type: 2
    }
}

export const REGION_ROUTE: RouteRecordRaw = {
    path: '/region',
    name: 'Region',
    component: () => import('@/views/region/index.vue'),
    meta: { title: '区域管理', hidden: false, type: 2 }
}

export const VERSION_ROUTE: RouteRecordRaw = {
    path: '/version',
    name: 'Version',
    component: () => import('@/views/version/index.vue'),
    meta: { title: '应用版本', hidden: false, type: 2 }
}
