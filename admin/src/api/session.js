import request from './request'

export function getSessionList(params) {
  return request.get('/api/admin/sessions', { params })
}

export function getSessionDetail(id) {
  return request.get(`/api/admin/sessions/${id}`)
}

export function createSession(data) {
  return request.post('/api/admin/sessions', data)
}

export function finishSession(id) {
  return request.put(`/api/admin/sessions/${id}/finish`)
}

export function cancelSession(id) {
  return request.put(`/api/admin/sessions/${id}/cancel`)
}


