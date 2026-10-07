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
    path: '/claim/:code',
    name: 'claim',
    component: () => import('@/pages/ClaimPage.vue'),
    meta: {
      action: 'manage',
      subject: 'device',
      title: 'Scan device',
      breadcrumb: [{ title: 'Devices', to: '/devices' }, { title: 'Scan device', disabled: true }],
    },
  },
  {
    path: '/overview',
    name: 'overview',
    component: () => import('@/pages/OverviewPage.vue'),
    meta: {
      action: 'manage',
      subject: 'device',
      title: 'Overview',
      breadcrumb: [{ title: 'Overview', disabled: true }],
    },
  },
  {
    path: '/branches',
    name: 'branch',
    component: () => import('@/pages/BranchesPage.vue'),
    meta: {
      action: 'manage',
      subject: 'device',
      title: 'Branches',
      breadcrumb: [{ title: 'Branches', disabled: true }],
    },
  },
  {
    path: '/devices',
    name: 'device',
    component: () => import('@/pages/DevicesPage.vue'),
    meta: {
      action: 'manage',
      subject: 'device',
      title: 'Devices',
      breadcrumb: [{ title: 'Devices', disabled: true }],
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
  { path: '/', redirect: '/devices' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  if (to.meta.public) return true
  if (!localStorage.getItem('accessToken')) {
    return { name: 'login', query: { next: to.fullPath } }
  }

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
