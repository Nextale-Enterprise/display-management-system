import { createRouter, createWebHistory } from 'vue-router'
import { useCommonStore } from '@/store/common'
import { ability } from '@/plugins/casl'

const routes = [
  { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { public: true, blank: true } },
  { path: '/forgot-password', name: 'forgot-password', component: () => import('@/pages/ForgotPasswordPage.vue'), meta: { public: true, blank: true } },
  {
    path: '/organizations',
    name: 'organization',
    component: () => import('@/pages/OrganizationsPage.vue'),
    meta: {
      action: 'manage',
      subject: 'organization',
      title: 'Organizations',
      breadcrumb: [{ title: 'Organizations', disabled: true }],
    },
  },
  {
    path: '/users',
    name: 'user',
    component: () => import('@/pages/UsersPage.vue'),
    meta: {
      action: 'manage',
      subject: 'user',
      title: 'Users',
      breadcrumb: [{ title: 'Users', disabled: true }],
    },
  },
  {
    path: '/screens',
    name: 'screen',
    component: () => import('@/pages/ScreensPage.vue'),
    meta: {
      action: 'manage',
      subject: 'screen',
      title: 'Screens',
      breadcrumb: [{ title: 'Screens', disabled: true }],
    },
  },
  {
    path: '/media',
    name: 'media',
    component: () => import('@/pages/MediaPage.vue'),
    meta: {
      action: 'manage',
      subject: 'media',
      title: 'Media',
      breadcrumb: [{ title: 'Media', disabled: true }],
    },
  },
  {
    path: '/playlists',
    name: 'playlist',
    component: () => import('@/pages/PlaylistsPage.vue'),
    meta: {
      action: 'manage',
      subject: 'playlist',
      title: 'Playlists',
      breadcrumb: [{ title: 'Playlists', disabled: true }],
    },
  },
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
