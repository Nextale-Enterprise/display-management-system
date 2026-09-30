import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'

export const useMediaStore = defineStore('media', {
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
        const response = await $api.raw('/api/media', { query: scopedQuery(this.query) })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const formData = new FormData()
      formData.append('name', params.name)
      const chosen = Array.isArray(params.file) ? params.file[0] : params.file
      formData.append('file', chosen)
      const scope = scopedQuery()
      if (scope.organization_id) formData.append('organization_id', scope.organization_id)
      for (const query of scope['queries[]'] || []) formData.append('queries[]', query)
      const response = await $api.raw('/api/media', { method: 'POST', body: formData })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/media/${id}`, { method: 'DELETE', query: scopedQuery() })
      await this.refreshList()
      return response
    },
  },
})
