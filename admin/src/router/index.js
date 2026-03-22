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
      {
        path: 'home',
        name: 'Home',
        component: () => import('@/views/Home.vue'),
        meta: { title: '首页' },
      },
      { path: 'admins', name: 'Admins', component: () => import('@/views/AdminList.vue'), meta: { title: '管理员管理' } },
      { path: 'roles', name: 'Roles', component: () => import('@/views/RoleList.vue'), meta: { title: '角色与权限' } },
      { path: 'banners', name: 'Banners', component: () => import('@/views/BannerList.vue'), meta: { title: '轮播图管理' } },
      { path: 'venues', name: 'Venues', component: () => import('@/views/VenueList.vue'), meta: { title: '钓场管理' } },
      { path: 'ponds', name: 'Ponds', component: () => import('@/views/PondList.vue'), meta: { title: '池塘管理' } },
      { path: 'orders', name: 'Orders', component: () => import('@/views/OrderList.vue'), meta: { title: '交易中心 - 钓场开卡订单' } },
      { path: 'shop/orders', name: 'ShopOrders', component: () => import('@/views/ShopOrderList.vue'), meta: { title: '交易中心 - 店铺商品订单' } },
      { path: 'sessions', name: 'Sessions', component: () => import('@/views/SessionList.vue'), meta: { title: '经营管理 - 开钓单' } },
      { path: 'return-logs', name: 'ReturnLogs', component: () => import('@/views/ReturnLogList.vue'), meta: { title: '经营管理 - 回鱼流水' } },
      { path: 'fish-trades', name: 'FishTrades', component: () => import('@/views/FishTradeList.vue'), meta: { title: '经营管理 - 卖鱼/收鱼流水' } },
      { path: 'config', name: 'Config', component: () => import('@/views/ConfigList.vue'), meta: { title: '全局配置' } },
      { path: 'shop/products', name: 'ShopProducts', component: () => import('@/views/ShopProductList.vue'), meta: { title: '店铺 — 公共商品库' } },
      { path: 'shop/venue', name: 'VenueShop', component: () => import('@/views/VenueShopList.vue'), meta: { title: '店铺 — 钓场选品与库存' } },
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
