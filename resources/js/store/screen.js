import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'

function tenantBody(params) {
  return { ...params, ...scopedQuery() }
}

export const useScreenStore = defineStore('screen', {
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
        const response = await $api.raw('/api/screens', { query: scopedQuery(this.query) })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const response = await $api.raw('/api/screens', { method: 'POST', body: tenantBody(params) })
      await this.refreshList()
      return response
    },
    async update(params) {
      const response = await $api.raw(`/api/screens/${params.id}`, { method: 'PUT', body: tenantBody(params) })
      await this.refreshList()
      return response
    },
    async regenerate(id) {
      const response = await $api.raw(`/api/screens/${id}/pairing-code`, { method: 'POST', body: tenantBody({}) })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/screens/${id}`, { method: 'DELETE', query: scopedQuery() })
      await this.refreshList()
      return response
    },
  },
})
