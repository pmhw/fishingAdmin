import request from './request'

export function getSessionList(params) {
  return request.get('/api/admin/sessions', { params })
}

export function getSessionDetail(id) {
  return request.get(`/api/admin/sessions/${id}`)
}

