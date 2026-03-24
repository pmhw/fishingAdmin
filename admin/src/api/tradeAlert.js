import request from './request'

/** 轮询：当前可见范围内「已支付」两类订单的最大主键 id（待支付不计入） */
export function getOrderAlertTip() {
  return request.get('/api/admin/trade/order-alert-tip')
}
