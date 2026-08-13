<script setup lang="ts">
import { filterExpiring } from './plugin-filters'
import PluginFilterList from './PluginFilterList.vue'
</script>

<template>
    <PluginFilterList :filter="(l) => filterExpiring(l)" empty-text="暂无即将到期插件" />
</template>
