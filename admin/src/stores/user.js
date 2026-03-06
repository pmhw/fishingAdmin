import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUserStore = defineStore('user', () => {
  const token = ref(localStorage.getItem('admin_token') || '')
  const user = ref(JSON.parse(localStorage.getItem('admin_user') || 'null'))

  function setLogin(t, u) {
    token.value = t
    user.value = u
    if (t) {
      localStorage.setItem('admin_token', t)
      localStorage.setItem('admin_user', JSON.stringify(u || {}))
    } else {
      localStorage.removeItem('admin_token')
      localStorage.removeItem('admin_user')
    }
  }

  /** 仅更新用户信息（含权限），不改 token；用于进入后台时拉取最新权限以显示菜单 */
  function setUser(u) {
    user.value = u
    if (token.value && u) {
      localStorage.setItem('admin_user', JSON.stringify(u))
    }
  }

  function logout() {
    setLogin('', null)
  }

  return { token, user, setLogin, setUser, logout }
})
