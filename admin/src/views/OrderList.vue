<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>交易中心 - 钓场开卡订单</span>
        </div>
      </template>

      <el-alert type="info" show-icon :closable="false" class="mb-12" title="本页仅展示开卡/钓费预付类订单；店铺商品单（单号 SO 开头）请在「店铺商品订单」中查看。" />

      <el-form inline class="filter-form">
        <el-form-item label="订单号">
          <el-input v-model="filters.order_no" placeholder="精确订单号" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width: 140px">
            <el-option label="待支付" value="pending" />
            <el-option label="已支付" value="paid" />
            <el-option label="已关闭" value="closed" />
            <el-option label="已退款" value="refund" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="order_no" label="订单号" min-width="180" show-overflow-tooltip />
        <el-table-column prop="user_nickname" label="用户昵称" min-width="120" show-overflow-tooltip />
        <el-table-column label="钓场/池塘" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.venue_name || '-' }}</span>
            <span v-if="row.pond_name"> / {{ row.pond_name }}</span>
          </template>
        </el-table-column>
        <el-table-column label="钓位" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.seat_code">{{ row.seat_code }}</span>
            <span v-else-if="row.seat_no">#{{ row.seat_no }}</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="金额(元)" width="110">
          <template #default="{ row }">
            <span>{{ row.amount_total_yuan?.toFixed?.(2) ?? (row.amount_total_yuan ?? 0) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="实付(元)" width="110">
          <template #default="{ row }">
            <span>{{ row.amount_paid_yuan?.toFixed?.(2) ?? (row.amount_paid_yuan ?? 0) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="pay_channel" label="支付渠道" width="110" />
        <el-table-column prop="pay_time" label="支付时间" width="170" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
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
import { getOrderList } from '@/api/order'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const filters = reactive({
  order_no: '',
  status: '',
})

function statusLabel(status) {
  const map = {
    pending: '待支付',
    paid: '已支付',
    closed: '已关闭',
    refund: '已退款',
  }
  return map[status] || status || '-'
}

function statusTagType(status) {
  const map = {
    pending: 'warning',
    paid: 'success',
    closed: 'info',
    refund: 'danger',
  }
  return map[status] || 'info'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getOrderList({
      page: page.value,
      limit: limit.value,
      order_no: filters.order_no || undefined,
      status: filters.status || undefined,
    })
    const data = res?.data ?? res
    list.value = data?.list ?? []
    total.value = data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.order_no = ''
  filters.status = ''
  page.value = 1
  fetchList()
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mb-12 {
  margin-bottom: 12px;
}
.filter-form {
  margin-bottom: 12px;
}
</style>

