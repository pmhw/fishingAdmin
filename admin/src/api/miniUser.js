import request from './request'

// 小程序用户列表（用于搜索选择）
export function searchMiniUsers(params) {
  return request.get('/api/admin/mini-users', { params })
}

