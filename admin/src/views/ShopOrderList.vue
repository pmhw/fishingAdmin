<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>交易中心 - 店铺商品订单</span>
        </div>
      </template>

      <el-form inline class="filter-form">
        <el-form-item label="订单号">
          <el-input v-model="filters.order_no" placeholder="精确订单号" clearable style="width: 200px" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width: 140px">
            <el-option label="待支付" value="pending" />
            <el-option label="已支付" value="paid" />
            <el-option label="已关闭" value="closed" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="order_no" label="订单号" min-width="190" show-overflow-tooltip />
        <el-table-column prop="user_nickname" label="用户" min-width="100" show-overflow-tooltip />
        <el-table-column prop="venue_name" label="钓场" min-width="140" show-overflow-tooltip />
        <el-table-column prop="pond_name" label="池塘" min-width="120" show-overflow-tooltip />
        <el-table-column prop="seat_display" label="座位号" width="120" show-overflow-tooltip />
        <el-table-column label="商品金额(元)" width="110">
          <template #default="{ row }">{{ formatYuan(row.amount_goods_yuan) }}</template>
        </el-table-column>
        <el-table-column label="余额抵扣(元)" width="110">
          <template #default="{ row }">{{ formatYuan(row.balance_deduct_yuan) }}</template>
        </el-table-column>
        <el-table-column label="微信应付(元)" width="110">
          <template #default="{ row }">{{ formatYuan(row.wx_amount_yuan) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" size="small">
              {{ statusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="支付渠道" width="100">
          <template #default="{ row }">{{ payChannelLabel(row.pay_channel) }}</template>
        </el-table-column>
        <el-table-column prop="pay_time" label="支付时间" width="170" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="90" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetail(row)">明细</el-button>
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

    <el-dialog v-model="detailVisible" title="订单明细" width="720px" destroy-on-close @opened="onDetailOpened">
      <div v-loading="detailLoading">
        <el-descriptions v-if="detail" :column="2" border size="small">
          <el-descriptions-item label="订单号">{{ detail.order_no }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ statusLabel(detail.status) }}</el-descriptions-item>
          <el-descriptions-item label="钓场">{{ detail.venue_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="用户">{{ detail.user_nickname || '-' }}</el-descriptions-item>
          <el-descriptions-item label="开钓单号">{{ detail.session_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="池塘">{{ detail.pond_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="座位号">{{ detail.seat_display || '—' }}</el-descriptions-item>
          <el-descriptions-item label="开钓单ID">{{ detail.fishing_session_id > 0 ? detail.fishing_session_id : '—' }}</el-descriptions-item>
          <el-descriptions-item label="商品合计">{{ formatYuan(detail.amount_goods_yuan) }} 元</el-descriptions-item>
          <el-descriptions-item label="余额抵扣">{{ formatYuan(detail.balance_deduct_yuan) }} 元</el-descriptions-item>
          <el-descriptions-item label="微信部分">{{ formatYuan(detail.wx_amount_yuan) }} 元</el-descriptions-item>
          <el-descriptions-item label="支付渠道">{{ payChannelLabel(detail.pay_channel) }}</el-descriptions-item>
          <el-descriptions-item label="支付时间" :span="2">{{ detail.pay_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ detail.remark || '-' }}</el-descriptions-item>
        </el-descriptions>
        <el-table v-if="detail?.items?.length" :data="detail.items" border size="small" style="margin-top: 12px">
          <el-table-column prop="product_name" label="商品" min-width="140" show-overflow-tooltip />
          <el-table-column prop="spec_label" label="规格" width="120" show-overflow-tooltip />
          <el-table-column label="单价(元)" width="100">
            <template #default="{ row }">{{ formatYuan(row.price_yuan) }}</template>
          </el-table-column>
          <el-table-column prop="quantity" label="数量" width="70" />
          <el-table-column label="小计(元)" width="100">
            <template #default="{ row }">{{ formatYuan(row.line_total_yuan) }}</template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { getShopOrderList, getShopOrderDetail } from '@/api/shopOrder'
import { useVenueContextStore } from '@/stores/venueContext'

const venueStore = useVenueContextStore()

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const filters = reactive({
  order_no: '',
  status: '',
})

const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref(null)
const detailId = ref(0)

function formatYuan(v) {
  const n = Number(v)
  if (Number.isNaN(n)) return '0.00'
  return n.toFixed(2)
}

function statusLabel(status) {
  const map = { pending: '待支付', paid: '已支付', closed: '已关闭' }
  return map[status] || status || '-'
}

function statusTagType(status) {
  const map = { pending: 'warning', paid: 'success', closed: 'info' }
  return map[status] || 'info'
}

function payChannelLabel(ch) {
  const map = { wx_mini: '微信支付', balance: '会员余额', mixed: '余额+微信' }
  return map[ch] || ch || '-'
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getShopOrderList({
      page: page.value,
      limit: limit.value,
      order_no: filters.order_no || undefined,
      status: filters.status || undefined,
      venue_id: venueStore.venueId || undefined,
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

function openDetail(row) {
  detailId.value = row.id
  detail.value = null
  detailVisible.value = true
}

async function onDetailOpened() {
  if (!detailId.value) return
  detailLoading.value = true
  try {
    const res = await getShopOrderDetail(detailId.value)
    const data = res?.data ?? res
    detail.value = data
  } finally {
    detailLoading.value = false
  }
}

watch(
  () => venueStore.venueId,
  () => {
    page.value = 1
    fetchList()
  }
)

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
.filter-form {
  margin-bottom: 12px;
}
</style>
