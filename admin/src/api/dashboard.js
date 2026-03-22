import request from './request'

/** 首页看板统计 venue_id 为 0 或不传表示全部钓场 */
export function getDashboardStats(params) {
  return request.get('/api/admin/dashboard/stats', { params })
}
