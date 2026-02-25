<template>
  <el-container class="layout">
    <el-aside width="200px" class="aside">
      <div class="logo">fishingAdmin</div>
      <el-menu :default-active="$route.path" :default-openeds="['permission']" router>
        <el-menu-item index="/home">首页</el-menu-item>
        <el-sub-menu index="permission">
          <template #title>权限中心</template>
          <el-menu-item index="/admins">管理员管理</el-menu-item>
          <el-menu-item index="/roles">角色与权限</el-menu-item>
        </el-sub-menu>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">
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
import { useUserStore } from '@/stores/user'
import { useRouter } from 'vue-router'
import { logout } from '@/api/auth'

const userStore = useUserStore()
const router = useRouter()

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
}
.logo {
  height: 56px;
  line-height: 56px;
  text-align: center;
  font-weight: bold;
  border-bottom: 1px solid #2a2a4a;
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
