import { ref, watch, onMounted, onUnmounted } from 'vue'
import { ElNotification } from 'element-plus'
import { getOrderAlertTip } from '@/api/tradeAlert'

const LS_SOUND = 'admin_order_alert_sound'
const LS_POPUP = 'admin_order_alert_popup'
/** 多门店时略拉长间隔，减轻服务端压力；仍远低于人工刷新页面 */
const POLL_MS = 30000

/** 短促提示音（不依赖音频文件；部分浏览器需用户先点击过页面才允许发声） */
export function playOrderBeep() {
  try {
    const AC = window.AudioContext || window.webkitAudioContext
    if (!AC) return
    const ctx = new AC()
    const runTone = (freq, t0, dur) => {
      const osc = ctx.createOscillator()
      const g = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.value = freq
      g.gain.setValueAtTime(0.0001, t0)
      g.gain.exponentialRampToValueAtTime(0.12, t0 + 0.02)
      g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur)
      osc.connect(g)
      g.connect(ctx.destination)
      osc.start(t0)
      osc.stop(t0 + dur + 0.02)
    }
    const now = ctx.currentTime
    runTone(880, now, 0.1)
    runTone(1174, now + 0.12, 0.12)
    setTimeout(() => {
      try {
        ctx.close()
      } catch (_) {}
    }, 500)
  } catch (_) {}
}

function readBoolLs(key, defaultVal) {
  try {
    const v = localStorage.getItem(key)
    if (v === null) return defaultVal
    return v === '1' || v === 'true'
  } catch (_) {
    return defaultVal
  }
}

/**
 * 后台打开时轮询新订单；需已登录且具备交易中心权限后调用
 * @param {import('vue-router').Router} router
 * @param {() => boolean} hasTradePermission
 */
export function useOrderNewAlert(router, hasTradePermission) {
  const enabled = ref(readBoolLs('admin_order_alert_enabled', true))
  const soundOn = ref(readBoolLs(LS_SOUND, true))
  const popupOn = ref(readBoolLs(LS_POPUP, true))

  let timer = null
  /** 因切换标签页/最小化而暂停，恢复可见时需继续轮询 */
  let pausedByHidden = false
  let baselineFish = null
  let baselineShop = null
  let primed = false

  function persist() {
    try {
      localStorage.setItem('admin_order_alert_enabled', enabled.value ? '1' : '0')
      localStorage.setItem(LS_SOUND, soundOn.value ? '1' : '0')
      localStorage.setItem(LS_POPUP, popupOn.value ? '1' : '0')
    } catch (_) {}
  }

  watch([enabled, soundOn, popupOn], persist)

  async function tick() {
    if (!enabled.value || !hasTradePermission()) return
    if (typeof document !== 'undefined' && document.hidden) return
    try {
      const res = await getOrderAlertTip()
      const data = res?.data ?? res
      const fish = Number(data?.fishing_order_max_id ?? 0)
      const shop = Number(data?.shop_order_max_id ?? 0)

      if (!primed) {
        baselineFish = fish
        baselineShop = shop
        primed = true
        return
      }

      const newFish = fish > baselineFish
      const newShop = shop > baselineShop
      if (newFish || newShop) {
        baselineFish = Math.max(baselineFish, fish)
        baselineShop = Math.max(baselineShop, shop)

        if (soundOn.value) {
          playOrderBeep()
        }

        if (popupOn.value) {
          const parts = []
          if (newShop) parts.push('店铺商品有新订单')
          if (newFish) parts.push('开卡/预付订单有新单')
          ElNotification({
            title: '新订单提醒',
            message: parts.join('；'),
            type: 'warning',
            duration: 12000,
            position: 'bottom-right',
            showClose: true,
            onClick: () => {
              if (newShop && !newFish) router.push('/shop/orders')
              else if (newFish && !newShop) router.push('/orders')
              else router.push('/shop/orders')
            },
          })
        }
      }
    } catch (_) {
      // 静默失败，避免打断使用
    }
  }

  function start() {
    stop()
    pausedByHidden = false
    if (!enabled.value || !hasTradePermission()) return
    primed = false
    baselineFish = null
    baselineShop = null
    tick()
    timer = window.setInterval(tick, POLL_MS)
  }

  function stop() {
    pausedByHidden = false
    if (timer != null) {
      clearInterval(timer)
      timer = null
    }
  }

  function onVisibilityChange() {
    if (typeof document === 'undefined') return
    if (document.hidden) {
      if (timer != null) {
        clearInterval(timer)
        timer = null
        pausedByHidden = true
      }
    } else if (pausedByHidden && enabled.value && hasTradePermission()) {
      pausedByHidden = false
      tick()
      timer = window.setInterval(tick, POLL_MS)
    }
  }

  onMounted(() => {
    document.addEventListener('visibilitychange', onVisibilityChange)
  })

  watch(enabled, (on) => {
    if (on && hasTradePermission()) start()
    else stop()
  })

  onUnmounted(() => {
    stop()
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })

  return {
    enabled,
    soundOn,
    popupOn,
    start,
    stop,
    /** 登录成功、权限就绪后调用一次 */
    restart() {
      if (enabled.value && hasTradePermission()) {
        start()
      } else {
        stop()
      }
    },
  }
}
