import request from './request'

export function getCaptcha() {
  return request.get('/api/admin/captcha')
}

export function login(data) {
  return request.post('/api/admin/login', data)
}

export function init(data) {
  return request.post('/api/admin/init', data)
}

export function getMe() {
  return request.get('/api/admin/me')
}

export function logout() {
  return request.post('/api/admin/logout')
}
