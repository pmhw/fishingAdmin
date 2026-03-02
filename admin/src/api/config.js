import request from './request'

export function getConfigList(params) {
  return request.get('/api/admin/configs', { params })
}

export function getConfigDetail(id) {
  return request.get(`/api/admin/configs/${id}`)
}

export function createConfig(data) {
  return request.post('/api/admin/configs', data)
}

export function updateConfig(id, data) {
  return request.put(`/api/admin/configs/${id}`, data)
}
