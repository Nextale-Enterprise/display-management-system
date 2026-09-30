import { createRouter, createWebHistory } from 'vue-router'
import { useCommonStore } from '@/store/common'
import { ability } from '@/plugins/casl'

const routes = [
  { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { public: true } },
  { path: '/organizations', name: 'organization', component: () => import('@/pages/OrganizationsPage.vue'), meta: { action: 'manage', subject: 'organization' } },
  { path: '/users', name: 'user', component: () => import('@/pages/UsersPage.vue'), meta: { action: 'manage', subject: 'user' } },
  { path: '/screens', name: 'screen', component: () => import('@/pages/ScreensPage.vue'), meta: { action: 'manage', subject: 'screen' } },
  { path: '/media', name: 'media', component: () => import('@/pages/MediaPage.vue'), meta: { action: 'manage', subject: 'media' } },
  { path: '/playlists', name: 'playlist', component: () => import('@/pages/PlaylistsPage.vue'), meta: { action: 'manage', subject: 'playlist' } },
  { path: '/', redirect: '/screens' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  if (to.meta.public) return true
  if (!localStorage.getItem('accessToken')) return { name: 'login' }

  const common = useCommonStore()
  if (!common.loginUserDetails.id) {
    try {
      await common.fetchUser()
    } catch {
      return { name: 'login' }
    }
  }

  if (to.meta.subject && !ability.can(to.meta.action || 'manage', to.meta.subject)) {
    return { name: common.homeRoute }
  }

  return true
})

export default router
