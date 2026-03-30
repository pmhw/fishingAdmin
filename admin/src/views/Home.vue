<template>
  <div class="page home fa-home-dashboard">
    <div class="home__head" @wheel.prevent="onHeadWheel">
      <header class="home__hero" role="banner">
        <div class="home__hero-top">
          <div class="home__hero-brand">
            <div class="home__hero-mark" aria-hidden="true">
              <el-icon :size="26"><DataBoard /></el-icon>
            </div>
            <div class="home__hero-text">
              <h2 class="home__hero-title">数据概览</h2>
              <p class="home__hero-desc">开卡、开钓单、回鱼等核心指标一览</p>
            </div>
          </div>
          <el-button type="primary" class="home__hero-btn" :loading="loading" @click="loadStats">
            <el-icon class="home__hero-btn-icon"><Refresh /></el-icon>
            <span>刷新数据</span>
          </el-button>
        </div>
        <div class="home__hero-foot" aria-label="统计范围说明">
          <div class="home__hero-scope">
            <span class="home__hero-scope-k">统计范围</span>
            <span class="home__hero-scope-v">{{ scopeHint }}</span>
          </div>
          <p class="home__hero-hint">{{ scopeTip }}</p>
        </div>
      </header>
    </div>

    <div ref="homeScrollRef" class="home__scroll" @wheel="onScrollAreaWheel">
    <el-skeleton v-if="loading && !stats" class="home__skeleton" animated>
      <template #template>
        <el-skeleton-item variant="h3" style="width: 40%; margin-bottom: 20px" />
        <el-row :gutter="20">
          <el-col v-for="n in 4" :key="n" :span="6">
            <el-skeleton-item variant="rect" style="width: 100%; height: 132px; border-radius: 16px" />
          </el-col>
        </el-row>
      </template>
    </el-skeleton>

    <div v-else-if="stats" :key="statsAnimKey" class="home__dashboard">
      <section v-for="(section, si) in statSections" :key="section.id" class="home__section">
        <header class="home__section-head">
          <span class="home__section-dot" :class="`home__section-dot--${section.tone}`" />
          <h3 class="home__section-title">{{ section.title }}</h3>
          <span class="home__section-line" />
        </header>
        <el-row :gutter="20" class="home__row">
          <el-col
            v-for="(item, idx) in section.items"
            :key="item.key"
            :xs="24"
            :sm="12"
            :md="item.md ?? 8"
            :lg="item.lg ?? 6"
          >
            <div
              class="stat-card"
              :class="[
                `stat-card--${item.tone}`,
                'stat-card--enter',
                { 'stat-card--clickable': !!item.to },
              ]"
              :style="{ '--i': flatIndex(si, idx) }"
              :role="item.to ? 'button' : undefined"
              :tabindex="item.to ? 0 : undefined"
              @click="statNavigate(item)"
              @keydown.enter.prevent="statNavigate(item)"
              @keydown.space.prevent="statNavigate(item)"
            >
              <div class="stat-card__top">
                <div class="stat-card__icon" :class="`stat-card__icon--${item.tone}`">
                  <el-icon :size="22"><component :is="item.icon" /></el-icon>
                </div>
                <span class="stat-card__label">{{ item.label }}</span>
              </div>
              <div class="stat-card__value" :class="item.valueClass">
                <span class="stat-card__value-num">
                  <CountUpText :value="item.value" :format="item.valueFormat" />
                </span>
                <span v-if="item.suffix" class="stat-card__suffix">{{ item.suffix }}</span>
              </div>
              <p class="stat-card__desc">{{ item.desc }}</p>
            </div>
          </el-col>
        </el-row>
      </section>
    </div>

    <el-empty v-else-if="!loading" class="home__empty" description="暂无数据" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import {
  DataBoard,
  Refresh,
  Wallet,
  Coin,
  TrendCharts,
  Histogram,
  Clock,
  Tickets,
  UserFilled,
  StarFilled,
  List,
  ScaleToOriginal,
} from '@element-plus/icons-vue'
import FishIcon from '@/components/icons/FishIcon.vue'
import CountUpText from '@/components/CountUpText.vue'
import { useRouter } from 'vue-router'
import { getDashboardStats } from '@/api/dashboard'
import { useVenueContextStore } from '@/stores/venueContext'
import { subscribeDashboardRefresh } from '@/composables/dashboardRefreshHub'

const router = useRouter()
const venueStore = useVenueContextStore()
const loading = ref(false)
const stats = ref(null)
/** 下方统计区滚动容器（标题区滚轮转发到这里，避免带动整块「数据概览」） */
const homeScrollRef = ref(null)

/**
 * 鼠标在标题/说明卡片上滚轮时，浏览器会把滚动交给外层，整块一起动。
 * 把 delta 转到 .home__scroll，并阻止默认行为。
 */
function onHeadWheel(e) {
  const el = homeScrollRef.value
  if (!el) return
  el.scrollTop += e.deltaY
}
/** 每次加载成功后递增，用于重播卡片入场动画 */
const statsAnimKey = ref(0)

const scopeHint = computed(() => {
  if (stats.value?.scope_label) return stats.value.scope_label
  if (venueStore.venueId && venueStore.venueName) return venueStore.venueName
  if (venueStore.venueId) return `钓场 #${venueStore.venueId}`
  return '全部钓场'
})

/** 与统计范围配套的短说明（重写板块后单独一行展示） */
const scopeTip = computed(() => {
  const base = venueStore.venueId
    ? '当前与顶部栏「当前钓场」筛选一致。'
    : '顶部未选钓场时，下方为全部钓场合计。'
  return `${base} 点击数据卡片可进入对应管理页（与顶部钓场筛选一致）。`
})

function flatIndex(sectionIdx, itemIdx) {
  let n = 0
  for (let i = 0; i < sectionIdx; i++) {
    n += statSections.value[i]?.items?.length ?? 0
  }
  return n + itemIdx
}

/** 点击卡片跳转对应管理页（与顶部「当前钓场」筛选一致，由目标页使用 venueStore） */
function statNavigate(item) {
  if (!item?.to) return
  router.push(item.to)
}

const statSections = computed(() => {
  if (!stats.value) return []
  const s = stats.value
  return [
    {
      id: 'revenue',
      title: '营收与实时',
      tone: 'teal',
      items: [
        {
          key: 'card_order_paid_yuan',
          label: '开卡订单实收',
          value: Number(s.card_order_paid_yuan) || 0,
          valueFormat: 'money',
          suffix: '元',
          desc: '已支付的开卡/钓费订单（不含店铺 SO）',
          tone: 'emerald',
          icon: Wallet,
          valueClass: 'stat-card__value--money',
          to: { name: 'Orders', query: { status: 'paid' } },
        },
        {
          key: 'session_paid_yuan',
          label: '开钓单实收合计',
          value: Number(s.session_paid_yuan) || 0,
          valueFormat: 'money',
          suffix: '元',
          desc: '开钓单累计实收金额',
          tone: 'cyan',
          icon: Coin,
          valueClass: 'stat-card__value--money',
          to: { name: 'Sessions' },
        },
        {
          key: 'session_ongoing_count',
          label: '在钓人数',
          value: Number(s.session_ongoing_count) || 0,
          valueFormat: 'int',
          desc: '进行中开钓单数量',
          tone: 'teal',
          icon: TrendCharts,
          valueClass: 'stat-card__value--accent',
          to: { name: 'Sessions', query: { status: 'ongoing' } },
        },
        {
          key: 'session_timeout_count',
          label: '超时钓卡',
          value: Number(s.session_timeout_count) || 0,
          valueFormat: 'int',
          desc: '当前状态为「超时」、待管理员手动结束的开钓单',
          tone: 'orange',
          icon: Clock,
          valueClass: 'stat-card__value--warn',
          to: { name: 'Sessions', query: { status: 'timeout' } },
        },
        {
          key: 'session_total_count',
          label: '开钓单总数',
          value: Number(s.session_total_count) || 0,
          valueFormat: 'int',
          desc: '全部状态开钓单',
          tone: 'slate',
          icon: Histogram,
          to: { name: 'Sessions' },
        },
      ],
    },
    {
      id: 'orders-users',
      title: '订单与用户',
      tone: 'blue',
      items: [
        {
          key: 'card_order_count',
          label: '开卡订单数',
          value: Number(s.card_order_count) || 0,
          valueFormat: 'int',
          desc: '开卡类订单笔数（不含 SO）',
          tone: 'indigo',
          icon: Tickets,
          to: { name: 'Orders' },
        },
        {
          key: 'consumer_user_count',
          label: '消费用户（去重）',
          value: Number(s.consumer_user_count) || 0,
          valueFormat: 'int',
          desc: '有过开钓单的用户数',
          tone: 'blue',
          icon: UserFilled,
          to: { name: 'Sessions' },
        },
        {
          key: 'vip_user_count',
          label: '其中会员用户',
          value: Number(s.vip_user_count) || 0,
          valueFormat: 'int',
          desc: 'is_vip=1 且有过开钓单',
          tone: 'amber',
          icon: StarFilled,
          valueClass: 'stat-card__value--vip',
          to: { name: 'Sessions' },
        },
        {
          key: 'return_log_count',
          label: '回鱼流水笔数',
          value: Number(s.return_log_count) || 0,
          valueFormat: 'int',
          desc: '回鱼记录条数',
          tone: 'violet',
          icon: List,
          to: { name: 'ReturnLogs' },
        },
      ],
    },
    {
      id: 'return',
      title: '回鱼统计',
      tone: 'violet',
      items: [
        {
          key: 'return_jin_qty',
          label: '回鱼斤数（按斤累计）',
          value: Number(s.return_jin_qty) || 0,
          valueFormat: 'qty',
          suffix: '斤',
          desc: 'return_type=斤 的数量合计',
          tone: 'green',
          icon: ScaleToOriginal,
          md: 8,
          lg: 8,
          to: { name: 'ReturnLogs' },
        },
        {
          key: 'return_tiao_qty',
          label: '回鱼条数（按条累计）',
          value: Number(s.return_tiao_qty) || 0,
          valueFormat: 'qty',
          suffix: '条',
          desc: 'return_type=条 的数量合计',
          tone: 'orange',
          icon: FishIcon,
          md: 8,
          lg: 8,
          to: { name: 'ReturnLogs' },
        },
        {
          key: 'return_amount_yuan',
          label: '回鱼金额',
          value: Number(s.return_amount_yuan) || 0,
          valueFormat: 'money',
          suffix: '元',
          desc: '回鱼流水金额合计',
          tone: 'rose',
          icon: Coin,
          valueClass: 'stat-card__value--money',
          md: 8,
          lg: 8,
          to: { name: 'ReturnLogs' },
        },
      ],
    },
  ]
})

/**
 * @param {{ silent?: boolean }} opts silent：新订单轮询触发的静默刷新，不重播卡片入场动画，数字用 CountUp 衔接
 */
async function loadStats(opts = {}) {
  const silent = opts.silent === true
  const hadStats = !!stats.value
  if (!silent || !hadStats) {
    loading.value = true
  }
  try {
    const res = await getDashboardStats({
      venue_id: venueStore.venueId || 0,
    })
    const data = res?.data ?? res
    stats.value = data && typeof data === 'object' ? data : null
    if (stats.value && !silent) {
      statsAnimKey.value += 1
    }
  } catch {
    stats.value = null
  } finally {
    loading.value = false
  }
}

watch(
  () => venueStore.venueId,
  () => {
    loadStats()
  }
)

let unsubDashboardRefresh = () => {}

onMounted(() => {
  loadStats()
  unsubDashboardRefresh = subscribeDashboardRefresh(() => {
    loadStats({ silent: true })
  })
})

/** 统计区滚到顶/底时阻止链式滚动，避免外层再动 */
function onScrollAreaWheel(e) {
  const el = e.currentTarget
  const { scrollTop, scrollHeight, clientHeight } = el
  const dy = e.deltaY
  const atTop = scrollTop <= 0
  const atBottom = scrollTop + clientHeight >= scrollHeight - 0.5
  if ((dy < 0 && atTop) || (dy > 0 && atBottom)) {
    e.preventDefault()
  }
}

onUnmounted(() => {
  unsubDashboardRefresh()
})
</script>

<style scoped>
/*
 * 与 global.css 中 .fa-home-dashboard 一致：首行固定、次行 minmax(0,1fr) 内滚动。
 * 标题区 @wheel.prevent + onHeadWheel 把滚轮交给 .home__scroll，避免「数据概览」跟着外层滑。
 */
.home {
  --stagger-step: 0.065s;
  flex: 1;
  min-height: 0;
  min-width: 0;
  width: 100%;
  max-width: 100%;
  height: 100%;
  max-height: 100%;
  box-sizing: border-box;
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  grid-template-rows: auto minmax(0, 1fr);
  gap: 24px 0;
  overflow-x: hidden;
  overflow-y: visible;
  overscroll-behavior: none;
}

.home__head {
  grid-row: 1;
  align-self: start;
}

.home__scroll {
  grid-row: 2;
  min-height: 0;
  min-width: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}

/* 抵消 Element Plus el-row gutter 负边距，避免略宽于父级产生横向滚动 */
.home :deep(.el-row) {
  margin-left: 0 !important;
  margin-right: 0 !important;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
}

.home :deep(.el-row > .el-col) {
  padding-left: 10px !important;
  padding-right: 10px !important;
  box-sizing: border-box;
}

.home__skeleton,
.home__empty {
  min-width: 0;
}

.home__dashboard {
  display: flex;
  flex-direction: column;
  gap: 0;
  min-width: 0;
}

/* ========== 数据概览顶栏（非 el-card，结构更简、便于固定区滚动策略） ========== */
.home__hero {
  margin: 0;
  padding: 0;
  border-radius: 16px;
  border: 1px solid rgba(13, 148, 136, 0.22);
  background: #fafefd;
  overflow: hidden;
  position: relative;
}

.home__hero::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  border-radius: 16px 0 0 16px;
  background: linear-gradient(180deg, #2dd4bf, #0d9488);
  pointer-events: none;
}

.home__hero-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px 20px;
  flex-wrap: wrap;
  padding: 18px 20px 16px 24px;
}

.home__hero-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  flex: 1;
}

.home__hero-mark {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: linear-gradient(145deg, #14b8a6 0%, #0d9488 55%, #0f766e 100%);
}

.home__hero-text {
  min-width: 0;
}

.home__hero-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: #0f172a;
  line-height: 1.25;
}

.home__hero-desc {
  margin: 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.45;
}

.home__hero-btn {
  flex-shrink: 0;
  border-radius: 12px !important;
  padding: 10px 18px !important;
  font-weight: 600 !important;
  display: inline-flex !important;
  align-items: center;
  gap: 6px;
}

.home__hero-btn-icon {
  font-size: 18px;
}

.home__hero-foot {
  padding: 12px 20px 16px 24px;
  border-top: 1px solid rgba(13, 148, 136, 0.1);
  background: rgba(255, 255, 255, 0.55);
}

.home__hero-scope {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 10px;
  margin-bottom: 8px;
}

.home__hero-scope-k {
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.home__hero-scope-v {
  font-size: 15px;
  font-weight: 700;
  color: #0d9488;
}

.home__hero-hint {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: #64748b;
  padding: 8px 12px;
  border-radius: 10px;
  background: rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(148, 163, 184, 0.2);
}

.home__skeleton {
  padding: 8px 0;
}

/* ========== Section ========== */
.home__section {
  margin-bottom: 28px;
}

.home__section:last-child {
  margin-bottom: 0;
}

.home__section-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.home__section-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.home__section-dot--teal {
  background: #14b8a6;
  color: #14b8a6;
}

.home__section-dot--blue {
  background: #3b82f6;
  color: #3b82f6;
}

.home__section-dot--violet {
  background: #8b5cf6;
  color: #8b5cf6;
}

.home__section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #334155;
  letter-spacing: 0.06em;
  white-space: nowrap;
}

.home__section-line {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, rgba(148, 163, 184, 0.45), transparent);
  min-width: 40px;
}

.home__row {
  margin-bottom: 0 !important;
}

/* ========== Stat cards ========== */
.stat-card {
  position: relative;
  margin-bottom: 20px;
  min-height: 138px;
  padding: 18px 18px 16px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.stat-card--clickable {
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
  user-select: none;
}

.stat-card--clickable:hover {
  border-color: rgba(13, 148, 136, 0.35);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.stat-card--clickable:focus-visible {
  outline: 2px solid #0d9488;
  outline-offset: 2px;
}

.stat-card--enter {
  animation: statCardIn 0.35s ease both;
  animation-delay: calc(var(--i, 0) * var(--stagger-step));
}

@keyframes statCardIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.stat-card__top {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  position: relative;
  z-index: 1;
}

.stat-card__icon {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.stat-card__icon--emerald {
  background: linear-gradient(135deg, #34d399, #059669);
}

.stat-card__icon--cyan {
  background: linear-gradient(135deg, #22d3ee, #0891b2);
}

.stat-card__icon--teal {
  background: linear-gradient(135deg, #2dd4bf, #0d9488);
}

.stat-card__icon--slate {
  background: linear-gradient(135deg, #94a3b8, #475569);
}

.stat-card__icon--indigo {
  background: linear-gradient(135deg, #818cf8, #4f46e5);
}

.stat-card__icon--blue {
  background: linear-gradient(135deg, #60a5fa, #2563eb);
}

.stat-card__icon--amber {
  background: linear-gradient(135deg, #fbbf24, #d97706);
}

.stat-card__icon--violet {
  background: linear-gradient(135deg, #a78bfa, #7c3aed);
}

.stat-card__icon--green {
  background: linear-gradient(135deg, #4ade80, #16a34a);
}

.stat-card__icon--orange {
  background: linear-gradient(135deg, #fb923c, #ea580c);
}

.stat-card__icon--rose {
  background: linear-gradient(135deg, #fb7185, #e11d48);
}

.stat-card__label {
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  line-height: 1.3;
}

.stat-card__value {
  position: relative;
  z-index: 1;
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.15;
  letter-spacing: -0.02em;
  font-variant-numeric: tabular-nums;
}

.stat-card__value-num {
  display: inline-block;
  animation: statValueIn 0.35s ease both;
  animation-delay: calc(var(--i, 0) * var(--stagger-step) + 0.08s);
}

@keyframes statValueIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.stat-card__value--money {
  color: #059669;
}

.stat-card__value--accent {
  color: #0d9488;
}

.stat-card__value--vip {
  color: #d97706;
}

.stat-card__value--warn {
  color: #ea580c;
}

.stat-card__suffix {
  margin-left: 4px;
  font-size: 15px;
  font-weight: 600;
  color: #94a3b8;
}

.stat-card__desc {
  position: relative;
  z-index: 1;
  margin: 10px 0 0;
  font-size: 12px;
  color: #94a3b8;
  line-height: 1.45;
}

.home__empty {
  padding: 48px 0;
}

.home__empty :deep(.el-empty__description) {
  color: #94a3b8;
}
</style>
