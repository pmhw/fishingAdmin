<template>
  <el-container class="layout">
    <el-aside :width="asideWidth" class="aside" :class="{ 'aside--collapsed': collapsed }">
      <div class="logo">
        <span v-show="!collapsed">fishingAdmin 1.0.0</span>
        <span v-show="collapsed" class="logo-short">F</span>
      </div>
      <el-menu
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
    <el-container>
      <el-header class="header">
        <el-button class="collapse-btn" :icon="collapsed ? Expand : Fold" text @click="toggleCollapse" />
        <span class="title">{{ $route.meta.title || '后台' }}</span>
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
        <div class="user">
          <span>{{ userStore.user?.nickname || userStore.user?.username }}</span>
          <el-button link type="primary" @click="onLogout">退出</el-button>
        </div>
      </el-header>
      <el-main class="main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>
<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
} from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useVenueContextStore } from '@/stores/venueContext'
import { useRouter } from 'vue-router'
import { logout, getMe } from '@/api/auth'
import { useOrderNewAlert } from '@/composables/useOrderNewAlert'

const STORAGE_KEY = 'admin_sidebar_collapsed'

const userStore = useUserStore()
const venueStore = useVenueContextStore()
const router = useRouter()
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
    hasPermission('admin.biz.session.manage')
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

const asideWidth = computed(() => (collapsed.value ? '64px' : '200px'))

const showPermissionMenu = computed(
  () => hasPermission('admin.user.list') || hasPermission('admin.role.manage')
)
const showContentMenu = computed(
  () => hasPermission('admin.banner.manage') || hasPermission('admin.venue.manage') || hasPermission('admin.pond.manage')
)
const showTradeMenu = computed(() => hasPermission('admin.trade.order.manage'))
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

/** 展开的子菜单（仅在有权限时包含，避免空菜单占位） */
const defaultOpeneds = computed(() => {
  const opens = []
  if (showPermissionMenu.value) opens.push('permission')
  if (showContentMenu.value) opens.push('content')
  if (showTradeMenu.value) opens.push('trade')
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
}
.aside {
  background: #1a1a2e;
  color: #fff;
  transition: width 0.2s ease;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.aside--collapsed .logo {
  padding: 0 8px;
}
.logo {
  height: 56px;
  line-height: 56px;
  text-align: center;
  font-weight: bold;
  border-bottom: 1px solid #2a2a4a;
  white-space: nowrap;
  transition: padding 0.2s ease;
  flex: 0 0 auto;
}
.logo-short {
  font-size: 20px;
}
.collapse-btn {
  margin-right: 12px;
  font-size: 18px;
}
.aside :deep(.el-menu) {
  border-right: none;
  background: transparent;
  flex: 1 1 auto;
  overflow-y: auto;
  overflow-x: hidden;
}
.aside :deep(.el-menu::-webkit-scrollbar) {
  width: 6px;
}
.aside :deep(.el-menu::-webkit-scrollbar-thumb) {
  background: rgba(255, 255, 255, 0.18);
  border-radius: 6px;
}
.aside :deep(.el-menu-item) {
  color: #a0a0a0;
}
.aside :deep(.el-menu-item.is-active) {
  color: #409eff;
  background: rgba(64, 158, 255, 0.1);
}
.aside :deep(.el-sub-menu__title) {
  color: #a0a0a0;
}
.aside :deep(.el-sub-menu .el-menu-item) {
  min-width: auto;
  padding-left: 50px;
}
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--el-border-color);
  padding: 0 16px;
}
.header .title {
  flex: 1;
  min-width: 120px;
}
.header-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 13px;
  color: var(--el-text-color-secondary);
}
.header-venue {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  max-width: min(520px, 100%);
  font-size: 13px;
}
.header-venue__current {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
  flex-shrink: 0;
  max-width: 200px;
}
.header-venue__label {
  color: var(--el-text-color-secondary);
  white-space: nowrap;
}
.header-venue__name {
  font-weight: 600;
  color: var(--el-color-primary);
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
}
.title {
  font-size: 16px;
}
.user {
  display: flex;
  align-items: center;
  gap: 12px;
}
.main {
  background: #f5f7fa;
  padding: 16px;
  overflow: auto;
}
</style>
