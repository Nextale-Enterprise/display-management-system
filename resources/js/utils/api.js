import { ofetch } from 'ofetch'
import { useCommonStore } from '@/store/common'

const baseURL = import.meta.env.DEV ? (import.meta.env.VITE_API_BASE_URL || '') : ''

export const $api = ofetch.create({
  baseURL,
  headers: {
    Accept: 'application/json',
  },
  async onRequest({ options }) {
    const token = localStorage.getItem('accessToken')
    if (token) {
      options.headers = {
        ...options.headers,
        Authorization: `Bearer ${token}`,
      }
    }
  },
  async onResponseError({ response, request, options }) {
    const url = String(request)
    if (response.status === 403 && !options._retry && !url.includes('/api/refresh')) {
      options._retry = true
      const refresh = await useCommonStore().refreshToken()
      if (refresh === false) {
        localStorage.removeItem('accessToken')
        if (!location.pathname.startsWith('/login')) location.assign('/login')
        throw response
      }
      const newToken = localStorage.getItem('accessToken')
      return ofetch(request, {
        ...options,
        headers: {
          ...options.headers,
          Authorization: `Bearer ${newToken}`,
        },
      })
    }

    if (response.status === 401 && !url.includes('/api/login')) {
      localStorage.removeItem('accessToken')
      if (!location.pathname.startsWith('/login')) location.assign('/login')
    }

    throw response
  },
})
