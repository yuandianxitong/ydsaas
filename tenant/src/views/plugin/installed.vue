<script setup lang="ts">
import { filterInstalled } from './plugin-filters'
import PluginFilterList from './PluginFilterList.vue'
</script>

<template>
    <PluginFilterList :filter="filterInstalled" empty-text="暂无已安装插件" />
</template>
