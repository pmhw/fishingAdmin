import request from './request'

// 订单列表
export function getOrderList(params) {
  return request.get('/api/admin/orders', { params })
}

