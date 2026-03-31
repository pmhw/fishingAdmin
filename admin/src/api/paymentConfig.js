import request from './request'

export function getPaymentConfig() {
  return request.get('/api/admin/payment-config')
}

export function savePaymentConfig(data) {
  return request.put('/api/admin/payment-config', data)
}

