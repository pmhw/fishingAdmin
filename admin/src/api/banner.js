import request from './request'

export function getBannerList(params) {
  return request.get('/api/admin/banners', { params })
}

export function getBannerDetail(id) {
  return request.get(`/api/admin/banners/${id}`)
}

export function createBanner(data) {
  return request.post('/api/admin/banners', data)
}

export function updateBanner(id, data) {
  return request.put(`/api/admin/banners/${id}`, data)
}

export function deleteBanner(id) {
  return request.delete(`/api/admin/banners/${id}`)
}
