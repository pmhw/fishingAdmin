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

/** 收费规则：按池塘查询 */
export function getPondFeeRules(pondId) {
  return request.get('/api/admin/pond-fee-rules', { params: { pond_id: pondId } })
}

/** 收费规则：添加 */
export function createPondFeeRule(data) {
  return request.post('/api/admin/pond-fee-rules', data)
}

/** 收费规则：编辑 */
export function updatePondFeeRule(id, data) {
  return request.put(`/api/admin/pond-fee-rules/${id}`, data)
}

/** 收费规则：删除 */
export function deletePondFeeRule(id) {
  return request.delete(`/api/admin/pond-fee-rules/${id}`)
}

/** 座位号：按池塘拉取（pond_seat） */
export function getPondSeats(pondId) {
  return request.get(`/api/admin/ponds/${pondId}/seats`)
}

/** 座位号：按区域一键生成/同步（写入 pond_seat） */
export function syncPondSeats(pondId) {
  return request.post(`/api/admin/ponds/${pondId}/seats/sync`)
}

export { uploadImage } from './venue'
