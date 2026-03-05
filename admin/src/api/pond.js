import request from './request'

export function getPondList(params) {
  return request.get('/api/admin/ponds', { params })
}

export function getPondDetail(id) {
  return request.get(`/api/admin/ponds/${id}`)
}

export function createPond(data) {
  return request.post('/api/admin/ponds', data)
}

export function updatePond(id, data) {
  return request.put(`/api/admin/ponds/${id}`, data)
}

export function deletePond(id) {
  return request.delete(`/api/admin/ponds/${id}`)
}

/** 拉取钓场列表（用于池塘表单选择所属钓场） */
export function getVenueOptions() {
  return request.get('/api/admin/venues', { params: { page: 1, limit: 500 } })
}

export { uploadImage } from './venue'
