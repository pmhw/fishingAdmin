import request from './request'

/** 轮询：当前可见范围内两类订单的最大主键 id */
export function getOrderAlertTip() {
  return request.get('/api/admin/trade/order-alert-tip')
}
