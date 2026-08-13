<template>
  <div class="diy-renderer" :style="rootStyle">
    <template v-for="(c, i) in list" :key="c.id">
      <DiyFloatButton v-if="c.type === 'float-button'" :props="c.props" />
      <div v-else :style="wrap(c)">
        <DiyBanner v-if="c.type === 'banner'" :props="c.props" />
        <DiyNavGrid v-else-if="c.type === 'nav-grid'" :props="c.props" />
        <DiyCategoryNav v-else-if="c.type === 'category-nav'" :props="c.props" />
        <DiyRichText v-else-if="c.type === 'rich-text'" :props="c.props" />
        <DiyTitleBar v-else-if="c.type === 'title-bar'" :props="c.props" />
        <DiyDivider v-else-if="c.type === 'divider'" :props="c.props" />
        <DiyImageAd v-else-if="c.type === 'image-ad'" :props="c.props" />
        <DiyImageCube v-else-if="c.type === 'image-cube'" :props="c.props" />
        <DiyHotzone v-else-if="c.type === 'hotzone'" :props="c.props" />
        <DiySearchBanner
          v-else-if="c.type === 'search-banner'"
          :props="c.props"
          :is-first="i === 0"
        />
        <DiyVideo v-else-if="c.type === 'video'" :props="c.props" />
        <DiyNotice v-else-if="c.type === 'notice'" :props="c.props" />
        <DiySearchBar v-else-if="c.type === 'search-bar'" :props="c.props" />
        <DiyPluginWidget v-else-if="c.props && c.props.render" :type="c.type" :props="c.props" />
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import DiyBanner from './DiyBanner.vue'
import DiyCategoryNav from './DiyCategoryNav.vue'
import DiyNavGrid from './DiyNavGrid.vue'
import DiyRichText from './DiyRichText.vue'
import DiyTitleBar from './DiyTitleBar.vue'
import DiyDivider from './DiyDivider.vue'
import DiyImageAd from './DiyImageAd.vue'
import DiyImageCube from './DiyImageCube.vue'
import DiyHotzone from './DiyHotzone.vue'
import DiySearchBanner from './DiySearchBanner.vue'
import DiyVideo from './DiyVideo.vue'
import DiyNotice from './DiyNotice.vue'
import DiySearchBar from './DiySearchBar.vue'
import DiyFloatButton from './DiyFloatButton.vue'
import DiyPluginWidget from './DiyPluginWidget.vue'
import { filterVisible } from './filterVisible'
import { componentStyleToCss } from './styleUtils'

const props = defineProps<{
  components?: Array<{ id: string; type: string; props: Record<string, any>; hidden?: boolean }>
  pageSettings?: Record<string, any>
}>()

const list = computed(() => filterVisible(props.components))
const rootStyle = computed(() => {
  const s = props.pageSettings ?? {}
  const css: Record<string, string> = {}
  if (s.background_color) css.backgroundColor = s.background_color
  if (s.background_image) css.backgroundImage = `url("${s.background_image}")`
  return css
})
function wrap(c: { props?: Record<string, any> }) {
  return componentStyleToCss(c.props?.componentStyle)
}
</script>
<style scoped>.diy-renderer { width: 100%; }</style>
