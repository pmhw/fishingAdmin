import axios from 'axios'
import { useUserStore } from '@/stores/user'
import router from '@/router'
import { ElMessage } from 'element-plus'

const request = axios.create({
  baseURL: import.meta.env.DEV ? '' : '', // 开发时走 vite proxy /api
  timeout: 10000,
})

request.interceptors.request.use((config) => {
  const store = useUserStore()
  if (store.token) {
    config.headers.Authorization = `Bearer ${store.token}`
  }
  return config
})

request.interceptors.response.use(
  (res) => {
    const { code, msg, data } = res.data ?? {}
    if (code !== 0 && code !== undefined) {
      ElMessage.error(msg || '请求失败')
      if (code === 401) {
        useUserStore().logout()
        router.push('/login')
      }
      return Promise.reject(new Error(msg))
    }
    return res.data
  },
  (err) => {
    ElMessage.error(err.message || '网络错误')
    return Promise.reject(err)
  }
)

export default request
