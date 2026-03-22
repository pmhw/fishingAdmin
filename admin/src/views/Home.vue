<template>
  <div class="page home">
    <el-card class="home__intro" shadow="never">
      <div class="home__intro-row">
        <div>
          <h2 class="home__title">数据概览</h2>
          <p class="home__subtitle">
            统计范围：<strong>{{ scopeHint }}</strong>
            <span v-if="venueStore.venueId" class="home__tip">（与顶部「当前钓场」一致）</span>
            <span v-else class="home__tip">（顶部未选钓场时为全部钓场合计）</span>
          </p>
        </div>
        <el-button type="primary" plain :loading="loading" @click="loadStats">刷新</el-button>
      </div>
    </el-card>

    <el-skeleton v-if="loading && !stats" :rows="6" animated />

    <template v-else-if="stats">
      <el-row :gutter="16" class="home__row">
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">开卡订单实收（元）</div>
            <div class="stat-card__value stat-card__value--money">{{ fmtMoney(stats.card_order_paid_yuan) }}</div>
            <div class="stat-card__desc">已支付的开卡/钓费订单（不含店铺 SO）</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">开钓单实收合计（元）</div>
            <div class="stat-card__value stat-card__value--money">{{ fmtMoney(stats.session_paid_yuan) }}</div>
            <div class="stat-card__desc">开钓单累计实收金额</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">在钓人数</div>
            <div class="stat-card__value stat-card__value--accent">{{ stats.session_ongoing_count }}</div>
            <div class="stat-card__desc">进行中开钓单数量</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">开钓单总数</div>
            <div class="stat-card__value">{{ stats.session_total_count }}</div>
            <div class="stat-card__desc">全部状态开钓单</div>
          </el-card>
        </el-col>
      </el-row>

      <el-row :gutter="16" class="home__row">
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">开卡订单数</div>
            <div class="stat-card__value">{{ stats.card_order_count }}</div>
            <div class="stat-card__desc">开卡类订单笔数（不含 SO）</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">消费用户（去重）</div>
            <div class="stat-card__value">{{ stats.consumer_user_count }}</div>
            <div class="stat-card__desc">有过开钓单的用户数</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">其中会员用户</div>
            <div class="stat-card__value stat-card__value--vip">{{ stats.vip_user_count }}</div>
            <div class="stat-card__desc">is_vip=1 且有过开钓单</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8" :lg="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">回鱼流水笔数</div>
            <div class="stat-card__value">{{ stats.return_log_count }}</div>
            <div class="stat-card__desc">回鱼记录条数</div>
          </el-card>
        </el-col>
      </el-row>

      <el-row :gutter="16" class="home__row home__row--last">
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">回鱼斤数（按斤累计）</div>
            <div class="stat-card__value">{{ fmtQty(stats.return_jin_qty) }} <span class="unit">斤</span></div>
            <div class="stat-card__desc">return_type=斤 的数量合计</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">回鱼条数（按条累计）</div>
            <div class="stat-card__value">{{ fmtQty(stats.return_tiao_qty) }} <span class="unit">条</span></div>
            <div class="stat-card__desc">return_type=条 的数量合计</div>
          </el-card>
        </el-col>
        <el-col :xs="24" :sm="12" :md="8">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-card__label">回鱼金额（元）</div>
            <div class="stat-card__value stat-card__value--money">{{ fmtMoney(stats.return_amount_yuan) }}</div>
            <div class="stat-card__desc">回鱼流水金额合计</div>
          </el-card>
        </el-col>
      </el-row>
    </template>

    <el-empty v-else-if="!loading" description="暂无数据" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { getDashboardStats } from '@/api/dashboard'
import { useVenueContextStore } from '@/stores/venueContext'

const venueStore = useVenueContextStore()
const loading = ref(false)
const stats = ref(null)

const scopeHint = computed(() => {
  if (stats.value?.scope_label) return stats.value.scope_label
  if (venueStore.venueId && venueStore.venueName) return venueStore.venueName
  if (venueStore.venueId) return `钓场 #${venueStore.venueId}`
  return '全部钓场'
})

function fmtMoney(v) {
  const n = Number(v)
  if (Number.isNaN(n)) return '0.00'
  return n.toFixed(2)
}

function fmtQty(v) {
  const n = Number(v)
  if (Number.isNaN(n)) return '0'
  return Number.isInteger(n) ? String(n) : n.toFixed(2)
}

async function loadStats() {
  loading.value = true
  try {
    const res = await getDashboardStats({
      venue_id: venueStore.venueId || 0,
    })
    const data = res?.data ?? res
    stats.value = data && typeof data === 'object' ? data : null
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

onMounted(() => {
  loadStats()
})
</script>

<style scoped>
.home__intro {
  margin-bottom: 16px;
}
.home__intro-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.home__title {
  margin: 0 0 8px;
  font-size: 20px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}
.home__subtitle {
  margin: 0;
  font-size: 14px;
  color: var(--el-text-color-regular);
  line-height: 1.5;
}
.home__tip {
  margin-left: 4px;
  font-size: 13px;
  color: var(--el-text-color-secondary);
}
.home__row {
  margin-bottom: 16px;
}
.home__row--last {
  margin-bottom: 0;
}
.stat-card {
  margin-bottom: 16px;
  min-height: 120px;
}
.stat-card__label {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-bottom: 8px;
}
.stat-card__value {
  font-size: 26px;
  font-weight: 700;
  color: var(--el-text-color-primary);
  line-height: 1.2;
}
.stat-card__value--money {
  color: var(--el-color-success);
}
.stat-card__value--accent {
  color: var(--el-color-primary);
}
.stat-card__value--vip {
  color: var(--el-color-warning);
}
.stat-card__value .unit {
  font-size: 14px;
  font-weight: 500;
  color: var(--el-text-color-secondary);
}
.stat-card__desc {
  margin-top: 10px;
  font-size: 12px;
  color: var(--el-text-color-placeholder);
  line-height: 1.4;
}
</style>
