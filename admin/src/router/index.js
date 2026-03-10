import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/stores/user'

const routes = [
  { path: '/login', name: 'Login', component: () => import('@/views/Login.vue'), meta: { public: true } },
  {
    path: '/',
    component: () => import('@/views/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/home' },
      { path: 'home', name: 'Home', component: () => import('@/views/Home.vue'), meta: { title: '首页' } },
      { path: 'admins', name: 'Admins', component: () => import('@/views/AdminList.vue'), meta: { title: '管理员管理' } },
      { path: 'roles', name: 'Roles', component: () => import('@/views/RoleList.vue'), meta: { title: '角色与权限' } },
      { path: 'banners', name: 'Banners', component: () => import('@/views/BannerList.vue'), meta: { title: '轮播图管理' } },
      { path: 'venues', name: 'Venues', component: () => import('@/views/VenueList.vue'), meta: { title: '钓场管理' } },
      { path: 'ponds', name: 'Ponds', component: () => import('@/views/PondList.vue'), meta: { title: '池塘管理' } },
      { path: 'orders', name: 'Orders', component: () => import('@/views/OrderList.vue'), meta: { title: '交易中心 - 订单管理' } },
      { path: 'config', name: 'Config', component: () => import('@/views/ConfigList.vue'), meta: { title: '全局配置' } },
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
