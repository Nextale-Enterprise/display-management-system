import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'

export const useOrganizationStore = defineStore('organization', {
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
        const response = await $api.raw('/api/organizations', {
          query: scopedQuery(this.query),
        })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const response = await $api.raw('/api/organizations', { method: 'POST', body: params })
      await this.refreshList()
      return response
    },
    async update(params) {
      const response = await $api.raw(`/api/organizations/${params.id}`, { method: 'PUT', body: params })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/organizations/${id}`, { method: 'DELETE' })
      await this.refreshList()
      return response
    },
  },
})
