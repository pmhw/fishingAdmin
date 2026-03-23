<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>经营管理 - 活动参与 / 抽号记录</span>
        </div>
      </template>

      <el-alert
        type="info"
        show-icon
        :closable="false"
        class="mb-12"
        title="展示 activity_participation：支付状态、抽号/占座结果、关联活动与用户；受池塘权限与顶部「当前钓场」筛选影响。"
      />

      <el-form inline class="filter-form">
        <el-form-item label="活动">
          <el-select
            v-model="filters.activity_id"
            placeholder="全部活动"
            clearable
            filterable
            style="width: 220px"
          >
            <el-option
              v-for="a in activityOptions"
              :key="a.id"
              :label="`${a.name} (#${a.id})`"
              :value="a.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="支付">
          <el-select v-model="filters.pay_status" placeholder="全部" clearable style="width: 120px">
            <el-option label="待支付" value="pending" />
            <el-option label="已支付" value="paid" />
            <el-option label="失败" value="failed" />
          </el-select>
        </el-form-item>
        <el-form-item label="抽号/分配">
          <el-select v-model="filters.draw_status" placeholder="全部" clearable style="width: 180px">
            <el-option label="待支付" value="waiting_paid" />
            <el-option label="待统一抽号" value="draw_waiting_unified" />
            <el-option label="待线下" value="draw_waiting_offline" />
            <el-option label="已分配钓位" value="assigned" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单号">
          <el-input v-model="filters.pay_order_no" placeholder="模糊匹配" clearable style="width: 180px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="activity_name" label="活动" min-width="140" show-overflow-tooltip />
        <el-table-column label="钓场/池塘" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.venue_name || '-' }}</span>
            <span v-if="row.pond_name"> / {{ row.pond_name }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="user_nickname" label="用户" width="120" show-overflow-tooltip />
        <el-table-column prop="fee_rule_name" label="收费档位" min-width="110" show-overflow-tooltip />
        <el-table-column prop="pay_order_no" label="订单号" min-width="170" show-overflow-tooltip />
        <el-table-column label="支付" width="88">
          <template #default="{ row }">
            <el-tag :type="payTag(row.pay_status)" size="small">{{ payLabel(row.pay_status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="抽号/分配" width="120">
          <template #default="{ row }">
            <span>{{ drawLabel(row.draw_status) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="意向/分配钓位" min-width="130">
          <template #default="{ row }">
            <template v-if="row.desired_seat_no || row.assigned_seat_no">
              <span v-if="row.desired_seat_no">意向 #{{ row.desired_seat_no }}</span>
              <span v-if="row.desired_seat_no && row.assigned_seat_no">；</span>
              <span v-if="row.assigned_seat_no">分配 #{{ row.assigned_seat_no }}</span>
            </template>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="assigned_session_id" label="开钓单ID" width="100" show-overflow-tooltip />
        <el-table-column label="积分领取" width="110">
          <template #default="{ row }">
            {{ row.claimed_points_at ? row.claimed_points_at : '未领' }}
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="报名时间" width="165" />
      </el-table>

      <el-pagination
        :current-page="page"
        :page-size="limit"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        style="margin-top: 16px"
        @current-change="(p) => { page = p; fetchList(); }"
        @size-change="(s) => { limit = s; page = 1; fetchList(); }"
      />
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { getActivityParticipationList, getActivityList } from '@/api/activity'
import { useVenueContextStore } from '@/stores/venueContext'

const venueStore = useVenueContextStore()

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const activityOptions = ref([])

const filters = reactive({
  activity_id: undefined,
  pay_status: '',
  draw_status: '',
  pay_order_no: '',
})

function payLabel(s) {
  const map = { pending: '待支付', paid: '已支付', failed: '失败' }
  return map[s] || s || '-'
}

function payTag(s) {
  const map = { pending: 'warning', paid: 'success', failed: 'danger' }
  return map[s] || 'info'
}

function drawLabel(s) {
  const map = {
    waiting_paid: '待支付',
    draw_waiting_unified: '待统一抽号',
    draw_waiting_offline: '待线下',
    assigned: '已分配',
    cancelled: '已取消',
  }
  return map[s] || s || '-'
}

async function loadActivities() {
  const params = { page: 1, limit: 500 }
  if (venueStore.venueId) params.venue_id = venueStore.venueId
  try {
    const res = await getActivityList(params)
    const data = res?.data ?? res
    activityOptions.value = data?.list ?? []
  } catch {
    activityOptions.value = []
  }
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: limit.value,
    }
    if (filters.activity_id) params.activity_id = filters.activity_id
    if (filters.pay_status) params.pay_status = filters.pay_status
    if (filters.draw_status) params.draw_status = filters.draw_status
    if (filters.pay_order_no?.trim()) params.pay_order_no = filters.pay_order_no.trim()
    if (venueStore.venueId) params.venue_id = venueStore.venueId

    const res = await getActivityParticipationList(params)
    const data = res?.data ?? res
    list.value = data?.list ?? []
    total.value = data?.total ?? 0
  } catch {
    list.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.activity_id = undefined
  filters.pay_status = ''
  filters.draw_status = ''
  filters.pay_order_no = ''
  page.value = 1
  fetchList()
}

watch(
  () => venueStore.venueId,
  () => {
    loadActivities()
    page.value = 1
    fetchList()
  }
)

onMounted(async () => {
  await loadActivities()
  fetchList()
})
</script>

<style scoped>
.mb-12 {
  margin-bottom: 12px;
}
.filter-form {
  margin-bottom: 12px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
</style>
