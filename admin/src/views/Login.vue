<template>
  <div class="login-wrap">
    <el-card class="login-card">
      <template #header>
        <span>fishingAdmin 后台登录</span>
      </template>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="0" @submit.prevent="onSubmit">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="账号" size="large" prefix-icon="User" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" placeholder="密码" size="large" show-password prefix-icon="Lock" @keyup.enter="onSubmit" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" size="large" :loading="loading" style="width: 100%" @click="onSubmit">登录</el-button>
        </el-form-item>
      </el-form>
      <div class="init-tip">
        若尚未初始化，请先调用 <code>POST /api/admin/init</code> 创建首个管理员（或使用接口工具传入 username、password、nickname）。
      </div>
    </el-card>
  </div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'
import { login } from '@/api/auth'

const router = useRouter()
const route = useRoute()
const store = useUserStore()

const formRef = ref(null)
const loading = ref(false)
const form = reactive({ username: 'admin', password: '123456' })
const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

async function onSubmit() {
  await formRef.value?.validate().catch(() => {})
  loading.value = true
  try {
    const res = await login(form)
    store.setLogin(res.data.token, res.data.user)
    ElMessage.success('登录成功')
    router.push(route.query.redirect || '/')
  } finally {
    loading.value = false
  }
}
</script>
<style scoped>
.login-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
}
.login-card {
  width: 380px;
}
.login-card :deep(.el-card__header) {
  font-size: 18px;
  text-align: center;
}
.init-tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  margin-top: 12px;
}
.init-tip code {
  background: var(--el-fill-color-light);
  padding: 2px 6px;
  border-radius: 4px;
}
</style>
