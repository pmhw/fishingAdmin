<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>经营管理 - 开钓单</span>
        </div>
      </template>

      <el-form inline class="filter-form">
        <el-form-item label="单号">
          <el-input v-model="filters.session_no" placeholder="精确 session_no" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width: 160px">
            <el-option label="进行中" value="ongoing" />
            <el-option label="已结束" value="finished" />
            <el-option label="已结算" value="settled" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="钓位码">
          <el-input v-model="filters.seat_code" placeholder="seat_code" clearable style="width: 180px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="session_no" label="开钓单号" min-width="190" show-overflow-tooltip />
        <el-table-column prop="user_nickname" label="用户" width="130" show-overflow-tooltip />
        <el-table-column label="钓场/池塘" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.venue_name || '-' }}</span>
            <span v-if="row.pond_name"> / {{ row.pond_name }}</span>
          </template>
        </el-table-column>
        <el-table-column label="钓位" width="140" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.seat_code">{{ row.seat_code }}</span>
            <span v-else-if="row.seat_no">#{{ row.seat_no }}</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="应收(元)" width="110">
          <template #default="{ row }">{{ formatMoney(row.amount_total_yuan) }}</template>
        </el-table-column>
        <el-table-column label="实收(元)" width="110">
          <template #default="{ row }">{{ formatMoney(row.amount_paid_yuan) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="start_time" label="开始时间" width="170" />
        <el-table-column prop="end_time" label="结束时间" width="170" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="goReturnLogs(row)">回鱼流水</el-button>
            <el-button link type="primary" @click="goFishTrades(row)">卖鱼流水</el-button>
          </template>
        </el-table-column>
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
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getSessionList } from '@/api/session'

const router = useRouter()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const filters = reactive({
  session_no: '',
  status: '',
  seat_code: '',
})

function statusLabel(status) {
  const map = { ongoing: '进行中', finished: '已结束', settled: '已结算', cancelled: '已取消' }
  return map[status] || status || '-'
}

function statusTagType(status) {
  const map = { ongoing: 'success', finished: 'info', settled: 'warning', cancelled: 'danger' }
  return map[status] || 'info'
}

function formatMoney(v) {
  const n = Number(v || 0)
  return n.toFixed(2)
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getSessionList({
      page: page.value,
      limit: limit.value,
      session_no: filters.session_no || undefined,
      status: filters.status || undefined,
      seat_code: filters.seat_code || undefined,
    })
    const data = res?.data ?? res
    list.value = data?.list ?? []
    total.value = data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.session_no = ''
  filters.status = ''
  filters.seat_code = ''
  page.value = 1
  fetchList()
}

function goReturnLogs(row) {
  router.push({ path: '/return-logs', query: { session_id: row.id } })
}

function goFishTrades(row) {
  router.push({ path: '/fish-trades', query: { session_id: row.id, pond_id: row.pond_id || '' } })
}

onMounted(fetchList)
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.filter-form {
  margin-bottom: 12px;
}
</style>

