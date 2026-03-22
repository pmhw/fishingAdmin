<template>
  <span class="count-up-text">{{ text }}</span>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'

const props = defineProps({
  /** 目标数值 */
  value: { type: Number, default: 0 },
  /** int：整数；money：两位小数；qty：整数或两位小数（与 fmtQty 一致） */
  format: {
    type: String,
    default: 'int',
    validator: (v) => ['int', 'money', 'qty'].includes(v),
  },
  /** 动画时长 ms */
  duration: { type: Number, default: 900 },
})

const display = ref(0)
let rafId = null

function easeOutCubic(t) {
  return 1 - Math.pow(1 - t, 3)
}

function formatOutput(n) {
  const x = Number(n)
  if (!Number.isFinite(x)) return props.format === 'money' ? '0.00' : '0'
  if (props.format === 'money') return x.toFixed(2)
  if (props.format === 'qty') {
    const r = Math.round(x)
    if (Math.abs(x - r) < 1e-9) return String(r)
    return x.toFixed(2)
  }
  return String(Math.round(x))
}

const text = computed(() => formatOutput(display.value))

function animateTo(target) {
  const to = Number(target)
  if (!Number.isFinite(to)) {
    display.value = 0
    return
  }
  if (rafId != null) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  const from = display.value
  if (Math.abs(from - to) < 1e-9) {
    display.value = to
    return
  }

  const start = performance.now()
  const dur = Math.max(200, props.duration)

  function frame(now) {
    const t = Math.min(1, (now - start) / dur)
    const e = easeOutCubic(t)
    display.value = from + (to - from) * e
    if (t < 1) {
      rafId = requestAnimationFrame(frame)
    } else {
      display.value = to
      rafId = null
    }
  }
  rafId = requestAnimationFrame(frame)
}

watch(
  () => props.value,
  (v) => {
    animateTo(v ?? 0)
  },
  { immediate: true }
)

onUnmounted(() => {
  if (rafId != null) cancelAnimationFrame(rafId)
})
</script>

<style scoped>
.count-up-text {
  font-variant-numeric: tabular-nums;
}
</style>
