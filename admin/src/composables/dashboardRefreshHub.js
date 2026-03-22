/**
 * 新订单轮询命中时通知首页等刷新看板数据（与 Layout 中 useOrderNewAlert 解耦）
 */
const listeners = new Set()

export function subscribeDashboardRefresh(fn) {
  if (typeof fn !== 'function') return () => {}
  listeners.add(fn)
  return () => listeners.delete(fn)
}

export function notifyDashboardRefresh() {
  listeners.forEach((fn) => {
    try {
      fn()
    } catch (_) {
      /* ignore */
    }
  })
}
