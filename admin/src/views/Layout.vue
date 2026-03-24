<template>
  <el-container class="layout">
    <el-aside :width="asideWidth" class="aside" :class="{ 'aside--collapsed': collapsed }">
      <div class="logo">
        <div v-show="!collapsed" class="logo__mark" aria-hidden="true">
          <span class="logo__mark-inner">F</span>
        </div>
        <div v-show="!collapsed" class="logo__text">
          <span class="logo__title">fishingAdmin</span>
          <span class="logo__ver">v1.0</span>
        </div>
        <div v-show="collapsed" class="logo__mark logo__mark--solo" aria-hidden="true">
          <span class="logo__mark-inner">F</span>
        </div>
      </div>
      <el-menu
        class="aside-menu"
        :default-active="$route.path"
        :default-openeds="collapsed ? [] : defaultOpeneds"
        :collapse="collapsed"
        :collapse-transition="false"
        router
      >
        <el-menu-item index="/home">
          <el-icon><HomeFilled /></el-icon>
          <template #title>首页</template>
        </el-menu-item>
        <el-sub-menu v-if="showPermissionMenu" index="permission">
          <template #title>
            <el-icon><Key /></el-icon>
            <span>权限中心</span>
          </template>
          <el-menu-item v-if="hasPermission('admin.user.list')" index="/admins">
            <el-icon><User /></el-icon>
            <template #title>管理员管理</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.role.manage')" index="/roles">
            <el-icon><Setting /></el-icon>
            <template #title>角色与权限</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu v-if="showContentMenu" index="content">
          <template #title>
            <el-icon><Document /></el-icon>
            <span>内容管理</span>
          </template>
          <el-menu-item v-if="hasPermission('admin.banner.manage')" index="/banners">
            <el-icon><PictureFilled /></el-icon>
            <template #title>轮播图管理</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.venue.manage')" index="/venues">
            <el-icon><Location /></el-icon>
            <template #title>钓场管理</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.pond.manage')" index="/ponds">
            <el-icon><Grid /></el-icon>
            <template #title>池塘管理</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu v-if="showTradeMenu" index="trade">
          <template #title>
            <el-icon><Grid /></el-icon>
            <span>交易中心</span>
          </template>
          <el-menu-item v-if="hasPermission('admin.trade.order.manage')" index="/orders">
            <el-icon><Document /></el-icon>
            <template #title>钓场开卡订单</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.trade.order.manage')" index="/shop/orders">
            <el-icon><ShoppingBag /></el-icon>
            <template #title>店铺商品订单</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu v-if="showActivityCenterMenu" index="activity-center">
          <template #title>
            <el-icon><Calendar /></el-icon>
            <span>活动中心</span>
          </template>
          <el-menu-item index="/activities">
            <el-icon><Calendar /></el-icon>
            <template #title>活动管理</template>
          </el-menu-item>
          <el-menu-item index="/activity-participations">
            <el-icon><List /></el-icon>
            <template #title>活动记录</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu v-if="showBizMenu" index="biz">
          <template #title>
            <el-icon><Grid /></el-icon>
            <span>经营管理</span>
          </template>
          <el-menu-item v-if="hasPermission('admin.biz.session.manage')" index="/sessions">
            <el-icon><Document /></el-icon>
            <template #title>开钓单</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.biz.return.manage')" index="/return-logs">
            <el-icon><Document /></el-icon>
            <template #title>回鱼流水</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.biz.trade.manage')" index="/fish-trades">
            <el-icon><Document /></el-icon>
            <template #title>卖鱼/收鱼流水</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu v-if="showShopMenu" index="shop">
          <template #title>
            <el-icon><ShoppingCart /></el-icon>
            <span>店铺管理</span>
          </template>
          <el-menu-item v-if="hasPermission('admin.shop.product.manage')" index="/shop/products">
            <el-icon><Goods /></el-icon>
            <template #title>钓场公共商品库</template>
          </el-menu-item>
          <el-menu-item v-if="hasPermission('admin.shop.venue.manage')" index="/shop/venue">
            <el-icon><Sell /></el-icon>
            <template #title>钓场选品与库存</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu v-if="showMiscMenu" index="misc">
          <template #title>
            <el-icon><Grid /></el-icon>
            <span>杂项</span>
          </template>
          <el-menu-item v-if="hasPermission('admin.config.manage')" index="/config">
            <el-icon><Setting /></el-icon>
            <template #title>全局配置</template>
          </el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>
    <el-container class="layout__right">
      <el-header class="header" height="56px">
        <div class="header__left">
          <el-button class="collapse-btn" :icon="collapsed ? Expand : Fold" circle text @click="toggleCollapse" />
          <div class="title-wrap">
            <span class="title">{{ $route.meta.title || '后台' }}</span>
            <span class="title-sub">工作台</span>
          </div>
        </div>
        <div v-if="showVenueSelector" class="header-venue">
          <span class="header-venue__current" :title="venueStore.venueName || '未选择'">
            <span class="header-venue__label">当前钓场</span>
            <span class="header-venue__name">{{ venueStore.venueName || '未选择' }}</span>
          </span>
          <el-select
            v-model="venueSelectModel"
            class="header-venue__select"
            filterable
            clearable
            placeholder="检索并选择钓场"
            :loading="!venueStore.optionsLoaded"
          >
            <el-option v-for="v in venueStore.options" :key="v.id" :label="v.name" :value="v.id" />
          </el-select>
        </div>
        <div v-if="showTradeMenu" class="header-alert">
          <el-tooltip
            placement="bottom"
            content="约每 30 秒检测一次；切到其它标签页或最小化时会暂停轮询。多门店仍按你的钓场权限合并为 1 次请求。"
          >
            <span class="header-alert__label">新单提醒</span>
          </el-tooltip>
          <el-switch v-model="orderAlertEnabled" inline-prompt active-text="开" inactive-text="关" size="small" />
          <el-checkbox v-model="orderAlertPopup" size="small">弹窗</el-checkbox>
          <el-checkbox v-model="orderAlertSound" size="small">声音</el-checkbox>
        </div>
        <div class="header-user">
          <el-avatar :size="36" class="header-user__avatar">{{ userInitial }}</el-avatar>
          <div class="header-user__meta">
            <span class="header-user__name">{{ userStore.user?.nickname || userStore.user?.username }}</span>
            <span class="header-user__role">已登录</span>
          </div>
          <el-button class="header-user__logout" type="primary" plain size="small" round @click="onLogout">
            退出
          </el-button>
        </div>
      </el-header>
      <el-main class="main fa-admin-main">
        <div class="tabs-wrap">
          <el-tabs
            v-model="activeTabName"
            type="card"
            class="route-tabs"
            @tab-click="onTabClick"
            @tab-remove="onTabRemove"
          >
            <el-tab-pane
              v-for="tab in visitedTabs"
              :key="tab.path"
              :name="tab.path"
              :label="tab.title"
              :closable="tab.path !== '/home'"
            />
          </el-tabs>
          <el-button class="tabs-close-others" text size="small" @click="closeOtherTabs">关闭其他</el-button>
        </div>
        <div class="main__outlet">
          <router-view />
        </div>
      </el-main>
    </el-container>
  </el-container>
</template>
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import {
  Fold,
  Expand,
  HomeFilled,
  User,
  Key,
  Setting,
  Document,
  PictureFilled,
  Grid,
  Location,
  ShoppingCart,
  Goods,
  Sell,
  ShoppingBag,
  Calendar,
  List,
} from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useVenueContextStore } from '@/stores/venueContext'
import { useRouter, useRoute } from 'vue-router'
import { logout, getMe } from '@/api/auth'
import { useOrderNewAlert } from '@/composables/useOrderNewAlert'

const STORAGE_KEY = 'admin_sidebar_collapsed'

const userStore = useUserStore()
const venueStore = useVenueContextStore()
const router = useRouter()
const route = useRoute()
const collapsed = ref(false)

/** 是否拥有某权限（超级管理员 permissions 含 '*' 视为全部） */
function hasPermission(code) {
  const perms = userStore.user?.permissions
  if (!Array.isArray(perms)) return false
  if (perms.includes('*')) return true
  return perms.includes(code)
}

/** 顶部全局钓场：与 el-select 双向绑定 */
const venueSelectModel = computed({
  get: () => venueStore.venueId ?? undefined,
  set: (id) => {
    if (id == null || id === '') {
      venueStore.clearVenue()
      return
    }
    const v = venueStore.options.find((x) => Number(x.id) === Number(id))
    venueStore.setVenue(id, v?.name ?? '')
  },
})

const showVenueSelector = computed(
  () =>
    hasPermission('admin.pond.manage') ||
    hasPermission('admin.venue.manage') ||
    hasPermission('admin.shop.venue.manage') ||
    hasPermission('admin.trade.order.manage') ||
    hasPermission('admin.biz.session.manage') ||
    hasPermission('admin.activity.manage')
)

watch(
  () => userStore.token,
  (t) => {
    if (!t) {
      venueStore.clearVenue()
    }
  }
)

const {
  enabled: orderAlertEnabled,
  soundOn: orderAlertSound,
  popupOn: orderAlertPopup,
  restart: restartOrderAlert,
  stop: stopOrderAlert,
} = useOrderNewAlert(router, () => hasPermission('admin.trade.order.manage'))

const asideWidth = computed(() => (collapsed.value ? '72px' : '232px'))

const userInitial = computed(() => {
  const n = String(userStore.user?.nickname || userStore.user?.username || '?').trim()
  return n ? n.slice(0, 1).toUpperCase() : '?'
})

const showPermissionMenu = computed(
  () => hasPermission('admin.user.list') || hasPermission('admin.role.manage')
)
const showContentMenu = computed(
  () => hasPermission('admin.banner.manage') || hasPermission('admin.venue.manage') || hasPermission('admin.pond.manage')
)
const showTradeMenu = computed(() => hasPermission('admin.trade.order.manage'))
const showActivityCenterMenu = computed(() => hasPermission('admin.activity.manage'))
const showBizMenu = computed(
  () =>
    hasPermission('admin.biz.session.manage') ||
    hasPermission('admin.biz.return.manage') ||
    hasPermission('admin.biz.trade.manage')
)
const showMiscMenu = computed(() => hasPermission('admin.config.manage'))
const showShopMenu = computed(
  () => hasPermission('admin.shop.product.manage') || hasPermission('admin.shop.venue.manage')
)

const HOME_TAB_PATH = '/home'
const visitedTabs = ref([{ path: HOME_TAB_PATH, title: '首页' }])
const activeTabName = ref(route.path || HOME_TAB_PATH)

function getRouteTitle(to) {
  const t = to?.meta?.title
  if (typeof t === 'string' && t.trim() !== '') return t
  if (to?.name) return String(to.name)
  return '页面'
}

function syncRouteTab(to) {
  const path = to?.path || HOME_TAB_PATH
  const title = getRouteTitle(to)
  const idx = visitedTabs.value.findIndex((x) => x.path === path)
  if (idx >= 0) {
    visitedTabs.value[idx].title = title
  } else {
    visitedTabs.value.push({ path, title })
  }
  activeTabName.value = path
}

function onTabClick(tabPane) {
  const target = String(tabPane?.paneName || '')
  if (target && target !== route.path) {
    router.push(target)
  }
}

function onTabRemove(targetName) {
  const target = String(targetName || '')
  if (!target || target === HOME_TAB_PATH) return
  const idx = visitedTabs.value.findIndex((x) => x.path === target)
  if (idx < 0) return
  visitedTabs.value.splice(idx, 1)
  if (activeTabName.value === target) {
    const next = visitedTabs.value[idx] || visitedTabs.value[idx - 1] || visitedTabs.value[0]
    const go = next?.path || HOME_TAB_PATH
    activeTabName.value = go
    if (route.path !== go) router.push(go)
  }
}

function closeOtherTabs() {
  const keep = route.path || HOME_TAB_PATH
  visitedTabs.value = visitedTabs.value.filter((x) => x.path === HOME_TAB_PATH || x.path === keep)
  activeTabName.value = keep
}

watch(
  () => route.fullPath,
  () => {
    syncRouteTab(route)
  },
  { immediate: true }
)

/** 展开的子菜单（仅在有权限时包含，避免空菜单占位） */
const defaultOpeneds = computed(() => {
  const opens = []
  if (showPermissionMenu.value) opens.push('permission')
  if (showContentMenu.value) opens.push('content')
  if (showTradeMenu.value) opens.push('trade')
  if (showActivityCenterMenu.value) opens.push('activity-center')
  if (showBizMenu.value) opens.push('biz')
  if (showShopMenu.value) opens.push('shop')
  if (showMiscMenu.value) opens.push('misc')
  return opens
})

function toggleCollapse() {
  collapsed.value = !collapsed.value
  try {
    localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0')
  } catch (_) {}
}

onMounted(async () => {
  try {
    collapsed.value = localStorage.getItem(STORAGE_KEY) === '1'
  } catch (_) {}
  venueStore.hydrateFromStorage()
  // 进入后台时拉取最新用户信息（含权限），保证分配角色/权限后菜单能正确显示
  if (userStore.token) {
    try {
      const res = await getMe()
      const userData = res?.data ?? res
      if (userData && typeof userData === 'object') {
        userStore.setUser(userData)
      }
    } catch (_) {
      // 未登录或 token 失效时 request 会 401，可忽略
    }
    if (showVenueSelector.value) {
      venueStore.loadOptions()
    }
  }
  restartOrderAlert()
})

async function onLogout() {
  stopOrderAlert()
  try {
    await logout()
  } catch (_) {}
  venueStore.clearVenue()
  userStore.logout()
  router.push('/login')
}
</script>
<style scoped>
.layout {
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
  background: #f1f5f9;
}

/* 右侧：侧栏旁主区域，列方向填满视口且不把整页撑出滚动条 */
.layout__right {
  flex: 1;
  min-width: 0;
  min-height: 0;
  max-height: 100%;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.aside {
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 55%, #0f172a 100%);
  color: #e2e8f0;
  transition: width 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 4px 0 24px rgba(15, 23, 42, 0.12);
  z-index: 2;
}

.aside--collapsed .logo {
  padding: 0 10px;
  justify-content: center;
}
.aside--collapsed .logo__mark--solo {
  margin: 0 auto;
}

.logo {
  height: 56px;
  min-height: 56px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  padding: 0 16px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.12);
  flex: 0 0 auto;
  transition: padding 0.2s ease;
}

.logo__mark {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(13, 148, 136, 0.35);
}

.logo__mark--solo {
  width: 40px;
  height: 40px;
  border-radius: 12px;
}

.logo__mark-inner {
  font-size: 17px;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.02em;
}

.logo__text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.2;
  overflow: hidden;
}

.logo__title {
  font-size: 15px;
  font-weight: 700;
  color: #f8fafc;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

.logo__ver {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 500;
}

.aside--collapsed .logo__text {
  display: none;
}

.collapse-btn {
  color: #64748b;
  font-size: 18px;
}
.collapse-btn:hover {
  color: var(--el-color-primary);
  background: rgba(13, 148, 136, 0.08) !important;
}

.aside-menu {
  border-right: none !important;
}

.aside :deep(.el-menu) {
  border-right: none;
  background: transparent;
  flex: 1 1 auto;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 10px 8px 16px;
}

.aside :deep(.el-menu::-webkit-scrollbar) {
  width: 5px;
}
.aside :deep(.el-menu::-webkit-scrollbar-thumb) {
  background: rgba(148, 163, 184, 0.25);
  border-radius: 8px;
}

.aside :deep(.el-menu-item),
.aside :deep(.el-sub-menu__title) {
  color: #cbd5e1 !important;
  border-radius: 10px;
  margin: 2px 0;
  height: 44px;
  transition: background 0.15s ease, color 0.15s ease;
}

.aside :deep(.el-menu-item:hover),
.aside :deep(.el-sub-menu__title:hover) {
  background: rgba(148, 163, 184, 0.12) !important;
  color: #f1f5f9 !important;
}

.aside :deep(.el-menu-item.is-active) {
  color: #5eead4 !important;
  background: linear-gradient(90deg, rgba(45, 212, 191, 0.18), rgba(45, 212, 191, 0.06)) !important;
  font-weight: 600;
  box-shadow: inset 3px 0 0 #2dd4bf;
}

.aside :deep(.el-sub-menu.is-active > .el-sub-menu__title) {
  color: #e2e8f0 !important;
}

.aside :deep(.el-sub-menu .el-menu-item) {
  min-width: auto;
  padding-left: 48px !important;
}

.aside :deep(.el-menu--collapse .el-menu-item .el-icon),
.aside :deep(.el-menu--collapse .el-sub-menu__title .el-icon) {
  margin-right: 0;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  min-height: 56px;
  height: auto !important;
  padding: 10px 20px 10px 16px !important;
  background: rgba(255, 255, 255, 0.82);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset;
}

.header__left {
  display: flex;
  align-items: center;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.title-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.title {
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  letter-spacing: 0.02em;
  line-height: 1.2;
}

.title-sub {
  font-size: 12px;
  color: #94a3b8;
  font-weight: 500;
}

.header-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  font-size: 13px;
  color: #64748b;
  padding: 6px 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid var(--fa-border-subtle, rgba(15, 23, 42, 0.08));
}

.header-venue {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  max-width: min(520px, 100%);
  font-size: 13px;
  padding: 6px 12px;
  background: linear-gradient(135deg, rgba(13, 148, 136, 0.06), rgba(13, 148, 136, 0.02));
  border-radius: 12px;
  border: 1px solid rgba(13, 148, 136, 0.15);
}

.header-venue__current {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
  flex-shrink: 0;
  max-width: 200px;
}

.header-venue__label {
  color: #64748b;
  white-space: nowrap;
  font-size: 12px;
}

.header-venue__name {
  font-weight: 700;
  color: #0f766e;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-venue__select {
  min-width: 200px;
  max-width: 280px;
}

.header-alert__label {
  white-space: nowrap;
  cursor: default;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-left: 8px;
  border-left: 1px solid rgba(15, 23, 42, 0.08);
  margin-left: 4px;
}

.header-user__avatar {
  flex-shrink: 0;
  background: linear-gradient(135deg, #14b8a6, #0d9488) !important;
  color: #fff !important;
  font-weight: 700;
  font-size: 15px;
}

.header-user__meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.header-user__name {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.header-user__role {
  font-size: 11px;
  color: #94a3b8;
}

.header-user__logout {
  flex-shrink: 0;
}

.main {
  background-color: #f1f5f9;
  background-image:
    radial-gradient(ellipse 80% 50% at 0% -20%, rgba(13, 148, 136, 0.09), transparent),
    radial-gradient(ellipse 60% 40% at 100% 100%, rgba(59, 130, 246, 0.06), transparent);
  padding: 20px 24px 28px;
  /* 滚动交给 outlet / 各页面根节点；!important 压住 Element Plus .el-main { overflow: auto } */
  overflow: hidden !important;
  display: flex;
  flex-direction: column;
  flex: 1 1 0 !important;
  min-height: 0 !important;
}

.tabs-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  min-height: 40px;
}

.route-tabs {
  flex: 1;
  min-width: 0;
}

.route-tabs :deep(.el-tabs__header) {
  margin: 0;
}

.route-tabs :deep(.el-tabs__item) {
  max-width: 210px;
}

.tabs-close-others {
  flex-shrink: 0;
}

/* 占满主区域高度，子页面再决定整页滚或分区滚 */
.main__outlet {
  flex: 1 1 0;
  min-height: 0;
  min-width: 0;
  height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden !important;
}

/* 普通列表页：整页在 .page 内纵向滚动 */
.main__outlet > :deep(.page:not(.home)) {
  flex: 1 1 0;
  min-height: 0;
  min-width: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
}

/* 首页（fa-home-dashboard）：布局由 Home + global.css 的 grid 控制，勿 overflow:hidden 否则吸顶失效 */
.main__outlet > :deep(.home.fa-home-dashboard) {
  flex: 1 1 0;
  min-height: 0;
  min-width: 0;
}

/* 其它带 .home 的旧页（若有） */
.main__outlet > :deep(.home:not(.fa-home-dashboard)) {
  flex: 1 1 0;
  min-height: 0;
  min-width: 0;
  overflow: hidden !important;
  display: flex;
  flex-direction: column;
}
</style>

<style>
/* 与 el-main 同节点需更高优先级，压住 Element Plus 默认 overflow:auto（否则整块含「数据概览」一起滚） */
.layout .el-main.main {
  overflow: hidden !important;
}
</style>
