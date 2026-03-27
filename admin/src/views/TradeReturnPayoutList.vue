<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>交易中心 - 回鱼打款记录</span>
        </div>
      </template>

      <el-alert 
        type="info"
        show-icon
        :closable="false"
        class="mb-12"
        title="本页展示回鱼流水的打款记录：会员入余额、非会员微信转账（v3）。"
      />

      <el-form inline class="filter-form">
        <el-form-item label="回鱼ID">
          <el-input v-model="filters.id" placeholder="回鱼流水ID" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item label="开钓单ID">
          <el-input v-model="filters.session_id" placeholder="session_id" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.payout_status" placeholder="全部" clearable style="width: 140px">
            <el-option label="未打款" value="none" />
            <el-option label="处理中" value="pending" />
            <el-option label="成功" value="success" />
            <el-option label="失败" value="failed" />
            <el-option label="取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="方式">
          <el-select v-model="filters.payout_channel" placeholder="全部" clearable style="width: 120px">
            <el-option label="余额" value="balance" />
            <el-option label="微信" value="wechat" />
          </el-select>
        </el-form-item>
        <el-form-item label="out_bill_no">
          <el-input v-model="filters.payout_out_bill_no" placeholder="微信转账单号" clearable style="width: 220px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="回鱼ID" width="90" />
        <el-table-column prop="session_id" label="开钓单" width="90" />
        <el-table-column prop="pond_id" label="池塘" width="80" />
        <el-table-column prop="amount" label="回鱼金额(元)" width="120" />
        <el-table-column label="用户类型" width="110">
          <template #default="{ row }">
            <el-tag :type="Number(row.is_vip_user) === 1 ? 'warning' : 'info'" size="small">
              {{ Number(row.is_vip_user) === 1 ? '会员' : '非会员' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="打款状态" width="110">
          <template #default="{ row }">
            <el-tag :type="payoutTagType(row.payout_status)" size="small">
              {{ payoutLabel(row.payout_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="打款方式" width="110">
          <template #default="{ row }">
            <span v-if="row.payout_channel === 'balance'">余额</span>
            <span v-else-if="row.payout_channel === 'wechat'">微信</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="payout_out_bill_no" label="out_bill_no" min-width="180" show-overflow-tooltip />
        <el-table-column prop="payout_time" label="打款时间" width="170" />
        <el-table-column prop="payout_fail_reason" label="失败原因" min-width="160" show-overflow-tooltip />
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
import { getReturnLogList } from '@/api/returnLog'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const filters = reactive({
  id: '',
  session_id: '',
  payout_status: '',
  payout_channel: '',
  payout_out_bill_no: '',
})

function payoutLabel(status) {
  const map = {
    none: '未打款',
    pending: '处理中',
    success: '成功',
    failed: '失败',
    cancelled: '取消',
  }
  return map[status] || status || '-'
}

function payoutTagType(status) {
  const map = {
    none: 'info',
    pending: 'warning',
    success: 'success',
    failed: 'danger',
    cancelled: 'info',
  }
  return map[status] || 'info'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getReturnLogList({
      page: page.value,
      limit: limit.value,
      session_id: filters.session_id || undefined,
      // 简单支持按 id 精确筛选：复用 out_bill_no 的能力不够，这里仍通过接口分页结果中前端过滤
      payout_status: filters.payout_status || undefined,
      payout_channel: filters.payout_channel || undefined,
      payout_out_bill_no: filters.payout_out_bill_no || undefined,
    })
    const data = res?.data ?? res
    let rows = data?.list ?? []
    if (filters.id) {
      const idNum = Number(filters.id)
      if (Number.isFinite(idNum) && idNum > 0) {
        rows = rows.filter((r) => Number(r.id) === idNum)
      }
    }
    list.value = rows
    total.value = data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.id = ''
  filters.session_id = ''
  filters.payout_status = ''
  filters.payout_channel = ''
  filters.payout_out_bill_no = ''
  page.value = 1
  fetchList()
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
.mb-12 {
  margin-bottom: 12px;
}
</style>

