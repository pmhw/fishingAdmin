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

/** 钓位区域：按池塘查询 */
export function getPondRegions(pondId) {
  return request.get('/api/admin/pond-regions', { params: { pond_id: pondId } })
}

/** 钓位区域：添加 */
export function createPondRegion(data) {
  return request.post('/api/admin/pond-regions', data)
}

/** 钓位区域：删除 */
export function deletePondRegion(id) {
  return request.delete(`/api/admin/pond-regions/${id}`)
}

export { uploadImage } from './venue'
