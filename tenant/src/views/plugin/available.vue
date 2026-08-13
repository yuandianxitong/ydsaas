<script setup lang="ts">
import { filterAvailable } from './plugin-filters'
import PluginFilterList from './PluginFilterList.vue'
</script>

<template>
    <PluginFilterList :filter="filterAvailable" empty-text="暂无可用插件" />
</template>
