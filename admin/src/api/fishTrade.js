import request from './request'

export function getFishTradeList(params) {
  return request.get('/api/admin/fish-trade-logs', { params })
}

export function createFishTrade(data) {
  return request.post('/api/admin/fish-trade-logs', data)
}

export function updateFishTrade(id, data) {
  return request.put(`/api/admin/fish-trade-logs/${id}`, data)
}

export function deleteFishTrade(id) {
  return request.delete(`/api/admin/fish-trade-logs/${id}`)
}

