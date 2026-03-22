<template>
  <div class="login-page">
    <div class="login-page__bg" aria-hidden="true" />
    <div class="login-page__glow login-page__glow--1" aria-hidden="true" />
    <div class="login-page__glow login-page__glow--2" aria-hidden="true" />

    <el-card class="login-card" shadow="never">
      <div class="login-card__brand">
        <div class="login-card__logo">F</div>
        <div class="login-card__titles">
          <h1 class="login-card__title">fishingAdmin</h1>
          <p class="login-card__subtitle">钓场后台管理系统</p>
        </div>
      </div>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="0" class="login-form" @submit.prevent="onSubmit">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="账号" size="large" :prefix-icon="UserIcon" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码"
            size="large"
            show-password
            :prefix-icon="LockIcon"
            @keyup.enter="onSubmit"
          />
        </el-form-item>
        <el-form-item prop="captcha">
          <div class="captcha-row">
            <el-input
              v-model="form.captcha"
              placeholder="验证码"
              size="large"
              maxlength="4"
              show-word-limit
              style="flex: 1"
              @keyup.enter="onSubmit"
            />
            <div class="captcha-img-wrap" @click="loadCaptcha">
              <img v-if="captchaImage" :src="captchaImage" alt="验证码" class="captcha-img" />
              <span v-else class="captcha-loading">{{ captchaLoadFailed ? '点击重试' : '加载中...' }}</span>
            </div>
          </div>
        </el-form-item>
        <el-form-item class="login-form__submit">
          <el-button type="primary" size="large" :loading="loading" class="login-form__btn" @click="onSubmit">
            登录
          </el-button>
        </el-form-item>
      </el-form>

      <div class="init-tip">
        若尚未初始化，请先调用 <code>POST /api/admin/init</code> 创建首个管理员（或使用接口工具传入 username、password、nickname）。
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { User as UserIcon, Lock as LockIcon } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { login, getCaptcha } from '@/api/auth'

const router = useRouter()
const route = useRoute()
const store = useUserStore()

const formRef = ref(null)
const loading = ref(false)
const captchaImage = ref('')
const captchaLoadFailed = ref(false)
const form = reactive({
  username: 'admin',
  password: '123456',
  captcha_key: '',
  captcha: '',
})
const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
  captcha: [{ required: true, message: '请输入验证码', trigger: 'blur' }],
}

async function loadCaptcha() {
  captchaImage.value = ''
  form.captcha = ''
  captchaLoadFailed.value = false
  try {
    const res = await getCaptcha()
    form.captcha_key = res.data?.key ?? ''
    captchaImage.value = res.data?.image ?? ''
    if (!captchaImage.value) captchaLoadFailed.value = true
  } catch (_) {
    captchaLoadFailed.value = true
    ElMessage.error('验证码加载失败，请点击图片重试')
  }
}

async function onSubmit() {
  await formRef.value?.validate().catch(() => {})
  loading.value = true
  try {
    const res = await login({
      username: form.username,
      password: form.password,
      captcha_key: form.captcha_key,
      captcha: form.captcha,
    })
    store.setLogin(res.data.token, res.data.user)
    ElMessage.success('登录成功')
    router.push(route.query.redirect || '/')
  } catch (_) {
    loadCaptcha()
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadCaptcha()
})
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
  overflow: hidden;
  background: #0f172a;
}

.login-page__bg {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(15, 23, 42, 0.97) 0%, rgba(15, 23, 42, 0.88) 40%, rgba(15, 23, 42, 1) 100%),
    url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2314b8a6' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.login-page__glow {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.45;
  pointer-events: none;
}

.login-page__glow--1 {
  width: min(480px, 90vw);
  height: min(480px, 90vw);
  top: -12%;
  right: -8%;
  background: radial-gradient(circle, #14b8a6 0%, transparent 70%);
}

.login-page__glow--2 {
  width: min(400px, 80vw);
  height: min(400px, 80vw);
  bottom: -15%;
  left: -10%;
  background: radial-gradient(circle, #3b82f6 0%, transparent 70%);
  opacity: 0.25;
}

.login-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  border-radius: 20px !important;
  border: 1px solid rgba(255, 255, 255, 0.12) !important;
  background: rgba(255, 255, 255, 0.72) !important;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow:
    0 4px 24px rgba(15, 23, 42, 0.08),
    0 24px 48px -12px rgba(15, 23, 42, 0.18) !important;
  padding: 8px 4px 12px;
}

.login-card :deep(.el-card__body) {
  padding: 12px 28px 28px;
}

.login-card__brand {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 28px;
  padding: 8px 4px 0;
}

.login-card__logo {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);
  color: #fff;
  font-size: 24px;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 8px 24px rgba(13, 148, 136, 0.4);
  letter-spacing: -0.03em;
}

.login-card__titles {
  min-width: 0;
}

.login-card__title {
  margin: 0 0 4px;
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: 0.02em;
}

.login-card__subtitle {
  margin: 0;
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

.login-form :deep(.el-form-item) {
  margin-bottom: 20px;
}

.login-form__submit {
  margin-bottom: 0 !important;
  margin-top: 8px;
}

.login-form__btn {
  width: 100%;
  height: 46px;
  font-size: 16px;
  font-weight: 600;
  border-radius: 12px !important;
  letter-spacing: 0.08em;
}

.init-tip {
  font-size: 12px;
  color: #64748b;
  margin-top: 20px;
  line-height: 1.55;
}

.init-tip code {
  background: rgba(15, 23, 42, 0.06);
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  color: #0f766e;
}

.captcha-row {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.captcha-img-wrap {
  width: 124px;
  height: 40px;
  flex-shrink: 0;
  cursor: pointer;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.captcha-img-wrap:hover {
  border-color: var(--el-color-primary);
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
}

.captcha-img {
  width: 100%;
  height: 100%;
  object-fit: fill;
  display: block;
}

.captcha-loading {
  font-size: 12px;
  color: #94a3b8;
}
</style>
