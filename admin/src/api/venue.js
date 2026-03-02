import request from './request'

export function getVenueList(params) {
  return request.get('/api/admin/venues', { params })
}

export function getVenueDetail(id) {
  return request.get(`/api/admin/venues/${id}`)
}

export function createVenue(data) {
  return request.post('/api/admin/venues', data)
}

export function updateVenue(id, data) {
  return request.put(`/api/admin/venues/${id}`, data)
}

export function updateVenueStatus(id, status) {
  return request.put(`/api/admin/venues/${id}/status`, { status })
}

export function deleteVenue(id) {
  return request.delete(`/api/admin/venues/${id}`)
}

// 复用后台图片上传
export function uploadImage(file) {
  const formData = new FormData()
  formData.append('file', file)
  return request.post('/api/admin/upload/image', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}
