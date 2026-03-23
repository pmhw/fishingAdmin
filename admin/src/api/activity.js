import request from './request'

/** 活动列表 GET /api/admin/activities */
export function getActivityList(params) {
  return request.get('/api/admin/activities', { params })
}

/** 活动详情（含 fee_rules）GET /api/admin/activities/:id */
export function getActivityDetail(id) {
  return request.get(`/api/admin/activities/${id}`)
}

export function createActivity(data) {
  return request.post('/api/admin/activities', data)
}

export function updateActivity(id, data) {
  return request.put(`/api/admin/activities/${id}`, data)
}

export function publishActivity(id) {
  return request.post(`/api/admin/activities/${id}/publish`)
}

export function closeActivity(id) {
  return request.post(`/api/admin/activities/${id}/close`)
}

export function createActivityFeeRule(activityId, data) {
  return request.post(`/api/admin/activities/${activityId}/fee-rules`, data)
}

export function unifiedDrawStart(activityId) {
  return request.post(`/api/admin/activities/${activityId}/draw/start`)
}
