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
  return request.get('/api/admin/venue-options')
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

/** 回鱼规则：按池塘查询 */
export function getPondReturnRules(pondId) {
  return request.get('/api/admin/pond-return-rules', { params: { pond_id: pondId } })
}

/** 回鱼规则：添加 */
export function createPondReturnRule(data) {
  return request.post('/api/admin/pond-return-rules', data)
}

/** 回鱼规则：编辑 */
export function updatePondReturnRule(id, data) {
  return request.put(`/api/admin/pond-return-rules/${id}`, data)
}

/** 回鱼规则：删除 */
export function deletePondReturnRule(id) {
  return request.delete(`/api/admin/pond-return-rules/${id}`)
}

/** 放鱼记录：按池塘查询 */
export function getPondFeedLogs(pondId) {
  return request.get('/api/admin/pond-feed-logs', { params: { pond_id: pondId } })
}

/** 放鱼记录：添加 */
export function createPondFeedLog(data) {
  return request.post('/api/admin/pond-feed-logs', data)
}

/** 放鱼记录：编辑 */
export function updatePondFeedLog(id, data) {
  return request.put(`/api/admin/pond-feed-logs/${id}`, data)
}

/** 放鱼记录：删除 */
export function deletePondFeedLog(id) {
  return request.delete(`/api/admin/pond-feed-logs/${id}`)
}

/** 座位号：按池塘拉取（pond_seat） */
export function getPondSeats(pondId) {
  return request.get(`/api/admin/ponds/${pondId}/seats`)
}

/** 座位号：按区域一键生成/同步（写入 pond_seat） */
export function syncPondSeats(pondId) {
  return request.post(`/api/admin/ponds/${pondId}/seats/sync`)
}

/** 座位二维码：打包 zip 下载 */
export function downloadPondSeatQrsZip(pondId) {
  return request.post(`/api/admin/ponds/${pondId}/seats/qrcodes/zip`)
}

/** 座位二维码：清理已生成的二维码与 zip */
export function cleanupPondSeatQrs(pondId) {
  return request.delete(`/api/admin/ponds/${pondId}/seats/qrcodes/cleanup`)
}

export { uploadImage } from './venue'
