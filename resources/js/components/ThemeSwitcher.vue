<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useTheme } from 'vuetify'

const themes = [
  { name: 'light', icon: 'tabler-sun-high' },
  { name: 'dark', icon: 'tabler-moon-stars' },
  { name: 'system', icon: 'tabler-device-desktop-analytics' },
]

const theme = useTheme()
const stored = localStorage.getItem('theme')
const preference = ref(stored === 'dark' || stored === 'light' || stored === 'system' ? stored : 'light')
const selectedItem = ref([preference.value])

const currentIcon = computed(() => themes.find(item => item.name === preference.value)?.icon || 'tabler-sun-high')

function resolvedTheme(name) {
  if (name !== 'system') return name
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function applyPreference(name) {
  preference.value = name
  selectedItem.value = [name]
  localStorage.setItem('theme', name)
  theme.global.name.value = resolvedTheme(name)
}

function onSystemChange() {
  if (preference.value === 'system') theme.global.name.value = resolvedTheme('system')
}

let media
onMounted(() => {
  media = window.matchMedia('(prefers-color-scheme: dark)')
  media.addEventListener('change', onSystemChange)
  if (preference.value === 'system') applyPreference('system')
})

onUnmounted(() => {
  media?.removeEventListener('change', onSystemChange)
})
</script>

<template>
  <IconBtn
    size="default"
    :aria-label="`${preference} theme`"
    color="rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity))"
  >
    <VIcon :icon="currentIcon" />
    <VTooltip activator="parent" open-delay="1000" scroll-strategy="close">
      <span class="text-capitalize">{{ preference }}</span>
    </VTooltip>
    <VMenu activator="parent" offset="12px" :width="180">
      <VList v-model:selected="selectedItem" mandatory>
        <VListItem
          v-for="{ name, icon } in themes"
          :key="name"
          :value="name"
          :prepend-icon="icon"
          color="primary"
          @click="applyPreference(name)"
        >
          <VListItemTitle class="text-capitalize">{{ name }}</VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
</template>
