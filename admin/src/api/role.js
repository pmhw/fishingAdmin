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
