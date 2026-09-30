<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useCommonStore } from '@/store/common'
import { ability } from '@/plugins/casl'

const router = useRouter()
const common = useCommonStore()

const systemNav = [
  { title: 'Organizations', to: 'organization', subject: 'organization', icon: 'mdi-domain' },
  { title: 'Users', to: 'user', subject: 'user', icon: 'mdi-account-multiple' },
]
const organizationNav = [
  { title: 'Screens', to: 'screen', subject: 'screen', icon: 'mdi-monitor' },
  { title: 'Media', to: 'media', subject: 'media', icon: 'mdi-image-multiple' },
  { title: 'Playlists', to: 'playlist', subject: 'playlist', icon: 'mdi-playlist-play' },
]

const navItems = computed(() => {
  const source = common.panelSelected === 'organization' ? organizationNav : systemNav
  return source.filter(item => ability.can('manage', item.subject))
})

const orgLocked = computed(() => !common.isOperator && common.organizations.length <= 1)

function selectOrg(value) {
  common.setOrganization(value)
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
</script>

<template>
  <v-app>
    <v-navigation-drawer permanent width="260" color="surface">
      <div class="px-4 py-5">
        <div class="text-h6 text-primary">Signage</div>
        <div class="text-caption text-medium-emphasis">Control plane</div>
      </div>
      <v-divider />
      <v-list nav class="px-2">
        <v-list-item
          v-for="item in navItems"
          :key="item.to"
          :prepend-icon="item.icon"
          :title="item.title"
          :to="{ name: item.to }"
          rounded="lg"
          color="primary"
        />
      </v-list>
    </v-navigation-drawer>
    <v-app-bar flat color="surface" border="b">
      <div v-if="common.isOperator" class="panel-view-toggle d-flex align-center flex-shrink-0 ms-2 me-3">
        <v-btn
          size="small"
          class="panel-view-toggle__btn panel-view-toggle__btn--start"
          :variant="common.panelSelected === 'system' ? 'flat' : 'outlined'"
          :color="common.panelSelected === 'system' ? 'primary' : undefined"
          @click="common.setPanel('system')"
        >
          System
        </v-btn>
        <v-btn
          size="small"
          class="panel-view-toggle__btn panel-view-toggle__btn--end"
          :variant="common.panelSelected === 'organization' ? 'flat' : 'outlined'"
          :color="common.panelSelected === 'organization' ? 'primary' : undefined"
          @click="common.setPanel('organization')"
        >
          Organization
        </v-btn>
      </div>
      <v-autocomplete
        v-if="common.panelSelected === 'organization'"
        class="ms-2"
        style="max-width: 305px"
        label="Organization"
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
      <v-spacer />
      <span class="me-4 text-body-2">{{ common.loginUserDetails.username || common.loginUserDetails.email }}</span>
      <v-btn variant="text" @click="logout">Logout</v-btn>
    </v-app-bar>
    <v-main class="bg-background">
      <v-container v-if="common.needsOrganizationContext" class="py-6">
        <v-alert type="info" title="Select an organization" variant="tonal">
          Organization screens, media, and playlists stay inside the organization you pick.
        </v-alert>
      </v-container>
      <v-container v-else class="py-6">
        <router-view />
      </v-container>
    </v-main>
  </v-app>
</template>

<style scoped>
.panel-view-toggle__btn {
  min-width: 7.5rem;
  flex-shrink: 0;
  white-space: nowrap;
  letter-spacing: normal;
}

.panel-view-toggle__btn--start {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.panel-view-toggle__btn--end {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
  margin-inline-start: -1px;
}
</style>
