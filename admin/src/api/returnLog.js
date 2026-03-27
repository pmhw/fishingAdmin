import request from './request'

export function getReturnLogList(params) {
  return request.get('/api/admin/pond-return-logs', { params })
}

export function createReturnLog(data) {
  return request.post('/api/admin/pond-return-logs', data)
}

export function updateReturnLog(id, data) {
  return request.put(`/api/admin/pond-return-logs/${id}`, data)
}

export function deleteReturnLog(id) {
  return request.delete(`/api/admin/pond-return-logs/${id}`)
}

export function payoutReturnLog(id) {
  return request.post(`/api/admin/pond-return-logs/${id}/payout`)
}

