import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'

export const useUserStore = defineStore('user', {
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
        const response = await $api.raw('/api/users', { query: scopedQuery(this.query) })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const response = await $api.raw('/api/users', { method: 'POST', body: params })
      await this.refreshList()
      return response
    },
    async update(params) {
      const response = await $api.raw(`/api/users/${params.id}`, { method: 'PUT', body: params })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/users/${id}`, { method: 'DELETE' })
      await this.refreshList()
      return response
    },
  },
})
