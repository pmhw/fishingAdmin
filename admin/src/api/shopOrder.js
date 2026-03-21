import request from './request'

export function getShopOrderList(params) {
  return request.get('/api/admin/shop/orders', { params })
}

export function getShopOrderDetail(id) {
  return request.get(`/api/admin/shop/orders/${id}`)
}
