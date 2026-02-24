import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/stores/user'

const routes = [
  { path: '/login', name: 'Login', component: () => import('@/views/Login.vue'), meta: { public: true } },
  {
    path: '/',
    component: () => import('@/views/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/admins' },
      { path: 'admins', name: 'Admins', component: () => import('@/views/AdminList.vue') },
    ],
  },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to, from, next) => {
  const store = useUserStore()
  if (to.meta.public) {
    next()
    return
  }
  if (to.meta.requiresAuth && !store.token) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }
  next()
})

export default router
