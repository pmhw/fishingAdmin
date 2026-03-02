<template>
  <el-container class="layout">
    <el-aside :width="asideWidth" class="aside" :class="{ 'aside--collapsed': collapsed }">
      <div class="logo">
        <span v-show="!collapsed">fishingAdmin</span>
        <span v-show="collapsed" class="logo-short">F</span>
      </div>
      <el-menu
        :default-active="$route.path"
        :default-openeds="collapsed ? [] : ['permission', 'content', 'misc']"
        :collapse="collapsed"
        :collapse-transition="false"
        router
      >
        <el-menu-item index="/home">
          <el-icon><HomeFilled /></el-icon>
          <template #title>首页</template>
        </el-menu-item>
        <el-sub-menu index="permission">
          <template #title>
            <el-icon><Key /></el-icon>
            <span>权限中心</span>
          </template>
          <el-menu-item index="/admins">
            <el-icon><User /></el-icon>
            <template #title>管理员管理</template>
          </el-menu-item>
          <el-menu-item index="/roles">
            <el-icon><Setting /></el-icon>
            <template #title>角色与权限</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="content">
          <template #title>
            <el-icon><Document /></el-icon>
            <span>内容管理</span>
          </template>
          <el-menu-item index="/banners">
            <el-icon><Folder /></el-icon>
            <template #title>轮播图管理</template>
          </el-menu-item>
        </el-sub-menu>
        <el-sub-menu index="misc">
          <template #title>
            <el-icon><Setting /></el-icon>
            <span>杂项</span>
          </template>
          <el-menu-item index="/config">
            <el-icon><Setting /></el-icon>
            <template #title>全局配置</template>
          </el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">
        <el-button
          class="collapse-btn"
          :icon="collapsed ? Expand : Fold"
          circle
          text
          @click="toggleCollapse"
        />
        <span class="title">{{ $route.meta.title || '后台' }}</span>
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
import { ref, computed, onMounted } from 'vue'
import { Fold, Expand, HomeFilled, User, Key, Setting, Document, Folder } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { useRouter } from 'vue-router'
import { logout } from '@/api/auth'

const STORAGE_KEY = 'admin_sidebar_collapsed'

const userStore = useUserStore()
const router = useRouter()
const collapsed = ref(false)

const asideWidth = computed(() => (collapsed.value ? '64px' : '200px'))

function toggleCollapse() {
  collapsed.value = !collapsed.value
  try {
    localStorage.setItem(STORAGE_KEY, collapsed.value ? '1' : '0')
  } catch (_) {}
}

onMounted(() => {
  try {
    collapsed.value = localStorage.getItem(STORAGE_KEY) === '1'
  } catch (_) {}
})

async function onLogout() {
  try {
    await logout()
  } catch (_) {}
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
  border-bottom: 1px solid var(--el-border-color);
  padding: 0 16px;
}
.header .title {
  flex: 1;
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
}
</style>
