<template>
  <div class="page home">
    <el-card>
      <template #header>
        <span>首页</span>
      </template>
      <div class="welcome">
        <p>欢迎使用 fishingAdmin 后台管理系统</p>
        <p class="sub">请从左侧菜单进入相应功能。</p>
      </div>
    </el-card>
  </div>
</template>
<script setup>
</script>
<style scoped>
.home .welcome {
  padding: 24px 0;
  text-align: center;
}
.home .welcome p {
  margin: 0 0 8px;
  font-size: 16px;
  color: var(--el-text-color-primary);
}
.home .welcome .sub {
  font-size: 14px;
  color: var(--el-text-color-regular);
}
</style>
