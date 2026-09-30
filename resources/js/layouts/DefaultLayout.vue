<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useCommonStore } from '@/store/common'
import { ability } from '@/plugins/casl'
import ThemeSwitcher from '@/components/ThemeSwitcher.vue'
import foodtaleLogo from '../../images/foodtale.png'

const router = useRouter()
const route = useRoute()
const display = useDisplay()
const common = useCommonStore()

const systemNav = [
  { title: 'Organizations', to: 'organization', subject: 'organization', icon: 'tabler-building' },
  { title: 'Users', to: 'user', subject: 'user', icon: 'tabler-users' },
]
const organizationNav = [
  { title: 'Screens', to: 'screen', subject: 'screen', icon: 'tabler-device-desktop' },
  { title: 'Media', to: 'media', subject: 'media', icon: 'tabler-photo' },
  { title: 'Playlists', to: 'playlist', subject: 'playlist', icon: 'tabler-playlist' },
]

const navItems = computed(() => {
  const source = common.panelSelected === 'organization' ? organizationNav : systemNav
  return source.filter(item => ability.can('manage', item.subject))
})

const orgLocked = computed(() => !common.isOperator && common.organizations.length <= 1)
const isMobileNav = computed(() => display.width.value < 1280)
const pageTitle = computed(() => route.meta.title || '')
const breadcrumbs = computed(() => route.meta.breadcrumb || [])
const displayName = computed(() => common.loginUserDetails.name || common.loginUserDetails.username || common.loginUserDetails.email || '')

const collapsed = ref(localStorage.getItem('verticalNavCollapsed') === '1')
const navHovered = ref(false)
const overlayOpen = ref(false)
const scrolled = ref(false)

const shellClass = computed(() => ({
  'layout-vertical-nav-collapsed': collapsed.value && !isMobileNav.value,
}))

function selectOrg(value) {
  common.setOrganization(value)
}

function toggleCollapsed() {
  collapsed.value = !collapsed.value
  localStorage.setItem('verticalNavCollapsed', collapsed.value ? '1' : '0')
}

function onWindowScroll() {
  scrolled.value = window.scrollY > 8
}

async function logout() {
  try {
    await fetch('/api/logout', { headers: { Authorization: `Bearer ${localStorage.getItem('accessToken')}` } })
  } catch {
    // local session still ends
  }
  common.logout()
  router.push({ name: 'login' })
}

watch(() => route.name, () => {
  overlayOpen.value = false
})

onMounted(() => {
  onWindowScroll()
  window.addEventListener('scroll', onWindowScroll, { passive: true })
})

onUnmounted(() => {
  window.removeEventListener('scroll', onWindowScroll)
})
</script>

<template>
  <div class="layout-wrapper layout-nav-type-vertical layout-navbar-sticky" :class="shellClass">
    <aside
      class="layout-vertical-nav"
      :class="{ hovered: navHovered, visible: overlayOpen }"
      @mouseenter="navHovered = true"
      @mouseleave="navHovered = false"
    >
      <div class="nav-header">
        <RouterLink :to="{ name: common.homeRoute }" class="app-logo app-title-wrapper">
          <img :src="foodtaleLogo" alt="Foodtale Logo" class="app-logo-img">
          <h1 v-show="!collapsed || navHovered || isMobileNav" class="app-logo-title">Foodtale</h1>
        </RouterLink>
        <IconBtn v-show="!collapsed || navHovered" class="d-none d-lg-inline-flex header-action" @click="toggleCollapsed">
          <VIcon :icon="collapsed ? 'tabler-circle' : 'tabler-circle-dot'" size="20" />
        </IconBtn>
        <IconBtn class="d-lg-none" @click="overlayOpen = false">
          <VIcon icon="tabler-x" size="20" />
        </IconBtn>
      </div>
      <ul class="nav-items">
        <li v-for="item in navItems" :key="item.to" class="nav-link">
          <RouterLink :to="{ name: item.to }">
            <VIcon :icon="item.icon" class="nav-item-icon" size="24" />
            <span class="nav-item-title">{{ item.title }}</span>
          </RouterLink>
        </li>
      </ul>
    </aside>

    <div class="layout-content-wrapper">
      <header class="layout-navbar navbar-blur" :class="{ 'is-scrolled': scrolled }">
        <div class="navbar-content-container">
          <IconBtn class="d-lg-none" aria-label="Open navigation" @click="overlayOpen = true">
            <VIcon icon="tabler-menu-2" size="26" />
          </IconBtn>

          <div v-if="common.isOperator" class="panel-view-toggle d-flex align-center flex-shrink-0 me-3">
            <VBtn
              size="small"
              class="panel-view-toggle__btn panel-view-toggle__btn--start"
              :variant="common.panelSelected === 'system' ? 'flat' : 'outlined'"
              :color="common.panelSelected === 'system' ? 'primary' : undefined"
              @click="common.setPanel('system')"
            >
              System
            </VBtn>
            <VBtn
              size="small"
              class="panel-view-toggle__btn panel-view-toggle__btn--end"
              :variant="common.panelSelected === 'organization' ? 'flat' : 'outlined'"
              :color="common.panelSelected === 'organization' ? 'primary' : undefined"
              @click="common.setPanel('organization')"
            >
              Organization
            </VBtn>
          </div>

          <ThemeSwitcher class="d-none d-sm-block" />

          <VAutocomplete
            v-if="common.panelSelected === 'organization'"
            class="ms-sm-2 me-2"
            style="max-width: 305px"
            placeholder="Organization"
            :items="common.organizations"
            item-title="name"
            item-value="id"
            :model-value="common.organizationSelected ? Number(common.organizationSelected) : null"
            :disabled="orgLocked"
            hide-details
            density="compact"
            variant="outlined"
            @update:model-value="selectOrg"
          />

          <VSpacer class="d-none d-sm-block" />

          <VAvatar size="38" color="primary" variant="tonal" class="cursor-pointer">
            <VIcon icon="tabler-user" />
            <VMenu activator="parent" width="240" location="bottom end" offset="12px">
              <VList>
                <VListItem>
                  <div class="d-flex gap-2 align-center">
                    <VAvatar color="primary" variant="tonal">
                      <VIcon icon="tabler-user" />
                    </VAvatar>
                    <div>
                      <h6 class="text-h6">{{ displayName }}</h6>
                      <VListItemSubtitle class="text-capitalize text-disabled">
                        {{ common.loginUserDetails.role }}
                      </VListItemSubtitle>
                    </div>
                  </div>
                </VListItem>
                <VDivider class="my-2" />
                <div class="px-4 py-2">
                  <VBtn block size="small" color="error" append-icon="tabler-logout" @click="logout">
                    Logout
                  </VBtn>
                </div>
              </VList>
            </VMenu>
          </VAvatar>
        </div>
      </header>

      <main class="layout-page-content">
        <div v-if="pageTitle" class="page-content-breadcrumb">
          <h2>{{ pageTitle }}</h2>
          <VDivider vertical class="d-none d-sm-flex align-self-center" style="block-size: 1.25rem;" />
          <VBreadcrumbs :items="breadcrumbs" class="d-none d-sm-flex">
            <template #prepend>
              <RouterLink :to="{ name: common.homeRoute }" class="d-inline-flex text-medium-emphasis">
                <VIcon icon="tabler-home" size="20" />
              </RouterLink>
              <VIcon icon="tabler-chevron-right" size="16" />
            </template>
            <template #divider>
              <VIcon icon="tabler-chevron-right" size="16" />
            </template>
            <template #item="{ item }">
              <VBreadcrumbsItem :disabled="item.disabled">
                <span :class="item.disabled ? 'disabled-breadcrumb' : 'text-primary'">{{ item.title }}</span>
              </VBreadcrumbsItem>
            </template>
          </VBreadcrumbs>
        </div>

        <VAlert
          v-if="common.needsOrganizationContext"
          type="info"
          variant="tonal"
          title="Select an organization"
          text="Organization screens, media, and playlists stay inside the organization you pick."
        />
        <RouterView v-else />
      </main>

      <footer class="layout-footer">
        <div class="footer-content-container">
          <span class="d-flex align-center text-medium-emphasis">
            &copy; {{ new Date().getFullYear() }} Powered By
            <a
              href="https://nextale.com.my/"
              target="_blank"
              rel="noopener noreferrer"
              class="ms-1"
              style="color: #002fa7;"
            >Nextale</a>
          </span>
        </div>
      </footer>
    </div>

    <div class="layout-overlay" :class="{ visible: overlayOpen && isMobileNav }" @click="overlayOpen = false" />
  </div>
</template>
