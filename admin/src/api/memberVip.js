import request from './request'

export function getMemberVipSettings() {
  return request.get('/api/admin/member-vip-settings')
}

export function updateMemberVipSettings(data) {
  return request.put('/api/admin/member-vip-settings', data)
}
