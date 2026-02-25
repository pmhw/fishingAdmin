import request from './request'

export function getAdminList(params) {
  return request.get('/api/admin/admin-users', { params })
}

export function getAdminDetail(id) {
  return request.get(`/api/admin/admin-users/${id}`)
}

export function createAdmin(data) {
  return request.post('/api/admin/admin-users', data)
}

export function updateAdmin(id, data) {
  return request.put(`/api/admin/admin-users/${id}`, data)
}

export function deleteAdmin(id) {
  return request.delete(`/api/admin/admin-users/${id}`)
}
