import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { ability } from '@/plugins/casl'

const PANEL_SYSTEM = 'system'
const PANEL_ORGANIZATION = 'organization'

export const useCommonStore = defineStore('common', {
  state: () => ({
    loginUserDetails: {},
    organizationSelected: localStorage.getItem('organizationSelected') || null,
    panelSelected: localStorage.getItem('panelSelected') || null,
  }),

  getters: {
    isOperator: state => state.loginUserDetails.role === 'admin' || state.loginUserDetails.role === 'superadmin',
    organizations: state => state.loginUserDetails.organizations || [],
    shouldScopeToSelectedOrganization: state =>
      state.panelSelected === PANEL_ORGANIZATION && !!state.organizationSelected,
    needsOrganizationContext: state =>
      state.panelSelected === PANEL_ORGANIZATION && !state.organizationSelected,
    homeRoute() {
      if (this.isOperator && this.panelSelected !== PANEL_ORGANIZATION) return 'organization'
      return 'device'
    },
  },

  actions: {
    async authenticate(username, password) {
      const response = await $api.raw('/api/login', {
        method: 'POST',
        body: { username, password },
      })
      if (response._data.token) {
        localStorage.setItem('accessToken', response._data.token)
      }
      return response
    },

    async refreshToken() {
      try {
        const response = await $api.raw('/api/refresh', { method: 'POST' })
        if (!response._data?.token) return false
        localStorage.setItem('accessToken', response._data.token)
        return true
      } catch {
        return false
      }
    },

    async fetchUser() {
      const user = await $api('/api/user')
      this.loginUserDetails = user
      ability.update(user.abilities || [])

      const panels = user.panels || []
      if (!panels.includes(this.panelSelected)) {
        this.setPanel(panels[0] || PANEL_ORGANIZATION)
      }
      if (user.role !== 'admin' && user.role !== 'superadmin') {
        this.setPanel(PANEL_ORGANIZATION)
      }
      if ((user.organizations || []).length === 1) {
        this.setOrganization(String(user.organizations[0].id))
      }
      return user
    },

    setOrganization(organizationId) {
      this.organizationSelected = organizationId ? String(organizationId) : null
      if (this.organizationSelected) {
        localStorage.setItem('organizationSelected', this.organizationSelected)
      } else {
        localStorage.removeItem('organizationSelected')
      }
    },

    setPanel(panel) {
      this.panelSelected = panel
      localStorage.setItem('panelSelected', panel)
    },

    logout() {
      localStorage.removeItem('accessToken')
      this.loginUserDetails = {}
      ability.update([])
    },
  },
})
