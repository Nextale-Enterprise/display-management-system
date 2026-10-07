import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'
import { useCommonStore } from '@/store/common'

function tenantBody(params) {
  return { ...params, ...scopedQuery() }
}

export const useBranchStore = defineStore('branch', {
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
        const response = await $api.raw('/api/branches', { query: scopedQuery(this.query) })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const response = await $api.raw('/api/branches', { method: 'POST', body: tenantBody(params) })
      await this.refreshList()
      await useCommonStore().fetchUser()
      return response
    },
    async update(params) {
      const response = await $api.raw(`/api/branches/${params.id}`, { method: 'PUT', body: tenantBody(params) })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/branches/${id}`, { method: 'DELETE', query: scopedQuery() })
      await this.refreshList()
      return response
    },
  },
})
