import { defineStore } from 'pinia'
import { $api } from '@/utils/api'
import { scopedQuery } from '@/utils/query'

function tenantBody(params) {
  return { ...params, ...scopedQuery() }
}

export const usePlaylistStore = defineStore('playlist', {
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
        const response = await $api.raw('/api/playlists', { query: scopedQuery(this.query) })
        this.list = response._data || []
      } finally {
        this.isLoading = false
      }
    },
    async create(params) {
      const response = await $api.raw('/api/playlists', { method: 'POST', body: tenantBody(params) })
      await this.refreshList()
      return response._data
    },
    async update(params) {
      const response = await $api.raw(`/api/playlists/${params.id}`, { method: 'PUT', body: tenantBody(params) })
      await this.refreshList()
      return response
    },
    async addFile(id, file) {
      const formData = new FormData()
      const chosen = Array.isArray(file) ? file[0] : file
      formData.append('file', chosen)
      const scope = scopedQuery()
      if (scope.organization_id) formData.append('organization_id', scope.organization_id)
      for (const query of scope['queries[]'] || []) formData.append('queries[]', query)
      const response = await $api.raw(`/api/playlists/${id}/items`, { method: 'POST', body: formData })
      await this.refreshList()
      return response._data
    },
    async saveItems(id, items) {
      const response = await $api.raw(`/api/playlists/${id}/items`, {
        method: 'PUT',
        body: tenantBody({ items }),
      })
      await this.refreshList()
      return response
    },
    async publish(id) {
      const response = await $api.raw(`/api/playlists/${id}/publish`, {
        method: 'POST',
        body: tenantBody({}),
      })
      await this.refreshList()
      return response
    },
    async remove(id) {
      const response = await $api.raw(`/api/playlists/${id}`, { method: 'DELETE', query: scopedQuery() })
      await this.refreshList()
      return response
    },
  },
})
