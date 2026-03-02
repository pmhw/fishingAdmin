import request from './request'

export function getConfigList(params) {
  return request.get('/api/admin/configs', { params })
}

/** 按 key 批量取值，keys 逗号分隔，如 getConfigValues('amap_key,amap_security_code') */
export function getConfigValues(keys) {
  return request.get('/api/admin/configs/values', { params: { keys: typeof keys === 'string' ? keys : (Array.isArray(keys) ? keys.join(',') : '') } })
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
