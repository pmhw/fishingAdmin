import request from './request'

export function getRoleList() {
  return request.get('/api/admin/roles')
}

export function getRoleDetail(id) {
  return request.get(`/api/admin/roles/${id}`)
}

export function createRole(data) {
  return request.post('/api/admin/roles', data)
}

export function updateRole(id, data) {
  return request.put(`/api/admin/roles/${id}`, data)
}

export function deleteRole(id) {
  return request.delete(`/api/admin/roles/${id}`)
}

export function getPermissionList() {
  return request.get('/api/admin/permissions')
}

/** 角色可管理池塘 ID 列表（用于池塘权限细分） */
export function getRolePonds(id) {
  return request.get(`/api/admin/roles/${id}/ponds`)
}

/** 设置角色可管理池塘，pond_ids 为空=全部池塘，非空=仅这些池塘 */
export function updateRolePonds(id, data) {
  return request.put(`/api/admin/roles/${id}/ponds`, data)
}

/** 角色可管理钓场 ID 列表（用于权限细分） */
export function getRoleVenues(id) {
  return request.get(`/api/admin/roles/${id}/venues`)
}

/** 设置角色可管理钓场，venue_ids 为空=全部钓场，非空=仅这些钓场 */
export function updateRoleVenues(id, data) {
  return request.put(`/api/admin/roles/${id}/venues`, data)
}
