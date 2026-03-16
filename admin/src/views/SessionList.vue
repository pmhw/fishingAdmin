<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>经营管理 - 开钓单</span>
          <el-button type="primary" @click="openCreateDialog">手动开钓单</el-button>
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

    <el-dialog
      v-model="createDialogVisible"
      title="手动开钓单"
      width="560px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetCreateForm"
    >
      <div v-loading="createSubmitting" element-loading-text="提交中…">
        <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="110px">
          <el-form-item label="小程序用户ID" prop="mini_user_id">
            <el-input v-model="createForm.mini_user_id" placeholder="mini_user.id（暂用纯数字）" />
          </el-form-item>
          <el-form-item label="钓场" prop="venue_id">
            <el-select v-model="createForm.venue_id" placeholder="请选择钓场" style="width:100%" @change="onCreateVenueChange">
              <el-option v-for="v in venueOptions" :key="v.id" :label="v.name" :value="String(v.id)" />
            </el-select>
          </el-form-item>
          <el-form-item label="池塘" prop="pond_id">
            <el-select v-model="createForm.pond_id" placeholder="请选择池塘" style="width:100%" @change="onCreatePondChange">
              <el-option v-for="p in pondOptions" :key="p.id" :label="p.name" :value="String(p.id)" />
            </el-select>
          </el-form-item>
          <el-form-item label="钓位">
            <el-select v-model="createForm.seat_id" placeholder="可选" style="width:100%">
              <el-option v-for="s in seatOptions" :key="s.id" :label="`#${s.seat_no} (${s.code || ''})`" :value="String(s.id)" />
            </el-select>
          </el-form-item>
          <el-form-item label="收费规则">
            <el-select v-model="createForm.fee_rule_id" placeholder="可选（正钓/偷驴）" style="width:100%">
              <el-option
                v-for="f in feeRuleOptions"
                :key="f.id"
                :label="`${f.name}（${f.duration || ''} ￥${f.amount}）`"
                :value="String(f.id)"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="应收金额(元)">
            <el-input v-model="createForm.amount_total" placeholder="留空则按收费规则金额" />
          </el-form-item>
          <el-form-item label="押金(元)">
            <el-input v-model="createForm.deposit_total" placeholder="可选" />
          </el-form-item>
          <el-form-item label="会员余额抵扣">
            <el-switch v-model="createForm.use_balance" />
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="createForm.remark" type="textarea" :rows="2" placeholder="选填" />
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <el-button :disabled="createSubmitting" @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="createSubmitting" @click="submitCreate">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { getSessionList, createSession } from '@/api/session'
import { getVenueOptions, getPondList, getPondSeats, getPondFeeRules } from '@/api/pond'

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

const createDialogVisible = ref(false)
const createFormRef = ref(null)
const createSubmitting = ref(false)
const createForm = reactive({
  mini_user_id: '',
  venue_id: '',
  pond_id: '',
  seat_id: '',
  fee_rule_id: '',
  amount_total: '',
  deposit_total: '',
  use_balance: true,
  remark: '',
})
const createRules = {
  mini_user_id: [{ required: true, message: '请输入小程序用户ID', trigger: 'blur' }],
  venue_id: [{ required: true, message: '请选择钓场', trigger: 'change' }],
  pond_id: [{ required: true, message: '请选择池塘', trigger: 'change' }],
}

const venueOptions = ref([])
const pondOptions = ref([])
const seatOptions = ref([])
const feeRuleOptions = ref([])

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

function openCreateDialog() {
  createDialogVisible.value = true
  if (!venueOptions.value.length) {
    loadVenues()
  }
}

function resetCreateForm() {
  if (createFormRef.value) createFormRef.value.clearValidate()
  createForm.mini_user_id = ''
  createForm.venue_id = ''
  createForm.pond_id = ''
  createForm.seat_id = ''
  createForm.fee_rule_id = ''
  createForm.amount_total = ''
  createForm.deposit_total = ''
  createForm.use_balance = true
  createForm.remark = ''
  pondOptions.value = []
  seatOptions.value = []
  feeRuleOptions.value = []
}

async function loadVenues() {
  try {
    const res = await getVenueOptions()
    const data = res?.data ?? res
    venueOptions.value = data?.list ?? []
  } catch {
    venueOptions.value = []
  }
}

async function onCreateVenueChange() {
  createForm.pond_id = ''
  createForm.seat_id = ''
  pondOptions.value = []
  seatOptions.value = []
  feeRuleOptions.value = []
  if (!createForm.venue_id) return
  try {
    const res = await getPondList({ page: 1, limit: 200, venue_id: createForm.venue_id })
    const data = res?.data ?? res
    pondOptions.value = data?.list ?? []
  } catch {
    pondOptions.value = []
  }
}

async function onCreatePondChange() {
  createForm.seat_id = ''
  seatOptions.value = []
  feeRuleOptions.value = []
  if (!createForm.pond_id) return
  try {
    const pondId = Number(createForm.pond_id)
    const [seatRes, feeRes] = await Promise.all([
      getPondSeats(pondId),
      getPondFeeRules(pondId),
    ])
    const seatData = seatRes?.data ?? seatRes
    seatOptions.value = seatData?.list ?? seatData ?? []
    const feeData = feeRes?.data ?? feeRes
    feeRuleOptions.value = feeData?.list ?? feeData ?? []
  } catch {
    seatOptions.value = []
    feeRuleOptions.value = []
  }
}

async function submitCreate() {
  if (!createFormRef.value) return
  await createFormRef.value.validate()
  try {
    createSubmitting.value = true
    const payload = {
      mini_user_id: Number(createForm.mini_user_id),
      venue_id: Number(createForm.venue_id),
      pond_id: Number(createForm.pond_id),
      seat_id: createForm.seat_id ? Number(createForm.seat_id) : undefined,
      fee_rule_id: createForm.fee_rule_id ? Number(createForm.fee_rule_id) : undefined,
      amount_total: createForm.amount_total || undefined,
      deposit_total: createForm.deposit_total || undefined,
      use_balance: createForm.use_balance,
      remark: createForm.remark || '',
    }
    const res = await createSession(payload)
    const data = res?.data ?? res
    const miniPayPath = data?.mini_pay_path
    const needPay = data?.need_pay ?? 0
    const deducted = data?.balance_deduct ?? 0
    let msg = `开钓单创建成功。余额抵扣：${deducted} 元`
    if (needPay > 0 && miniPayPath) {
      msg += `，还需微信支付：${needPay} 元。\n小程序支付路径：${miniPayPath}`
    }
    ElMessage.success(msg)
    createDialogVisible.value = false
    fetchList()
  } catch (e) {
    console.error(e)
  } finally {
    createSubmitting.value = false
  }
}

onMounted(() => {
  fetchList()
  loadVenues()
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

