import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'
import { useCommonStore } from '@/store/common'

function tenantBody(params) {
  return { ...params, ...scopedQuery() }
}

export const useDeviceStore = defineStore('device', {
  state: () => ({
    list: [],
    isLoading: false,
    query: [],
  }),
  getters: {
    getList: state => state.list,
    getIsLoading: state => state.isLoading,
  },
  actions: {
    async refreshList() {
      this.isLoading = true
      try {
        const response = await $api.raw('/api/devices', { query: scopedQuery(this.query) })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const response = await $api.raw('/api/devices', { method: 'POST', body: tenantBody(params) })
      await this.refreshList()
      await useCommonStore().fetchUser()
      return response
    },
    async update(params) {
      const response = await $api.raw(`/api/devices/${params.id}`, { method: 'PUT', body: tenantBody(params) })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/devices/${id}`, { method: 'DELETE', query: scopedQuery() })
      await this.refreshList()
      return response
    },
    async acceptClaim(code, organizationId, deviceId) {
      const response = await $api.raw(`/api/claims/${code}/accept`, {
        method: 'POST',
        body: { organization_id: organizationId, device_id: deviceId },
      })
      await this.refreshList()
      return response._data
    },
    async release(id) {
      const response = await $api.raw(`/api/devices/${id}/release`, { method: 'POST', body: tenantBody({}) })
      await this.refreshList()
      return response
    },
  },
})
