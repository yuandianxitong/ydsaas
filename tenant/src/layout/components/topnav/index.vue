<script setup lang="ts">
import Breadcrumb from '@/layout/components/header/breadcrumb.vue'
import TenantHeaderAlert from '@/layout/components/header/tenant-header-alert.vue'
import LayoutMain from '@/layout/components/main.vue'

import SubSidebar from './sub-sidebar.vue'
import Topbar from './topbar.vue'
</script>

<template>
    <div class="topnav-shell">
        <Topbar />
        <TenantHeaderAlert />
        <div class="body">
            <SubSidebar />
            <div class="content">
                <!-- v2.7.6：顶部布局展示面包屑（取代原 MultipleTabs），如「会员 / 会员管理」 -->
                <div class="content-crumb">
                    <Breadcrumb />
                </div>
                <LayoutMain class="content-main" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.topnav-shell {
    display: flex;
    flex-direction: column;
    height: 100vh;
}

.body {
    display: flex;
    flex: 1;
    min-height: 0;
}

.content {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.content-crumb {
    flex-shrink: 0;
    height: 40px;
    display: flex;
    align-items: center;
    padding: 0 16px;
    background: var(--el-bg-color);
    border-bottom: 1px solid var(--el-border-color-lighter);
    font-size: 13px;
}

.content-main {
    flex: 1;
    overflow: auto;
    min-height: 0;
}
</style>
