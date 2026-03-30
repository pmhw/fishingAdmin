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
            <el-option label="已超时" value="timeout" />
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
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.order_type === 'activity' ? 'warning' : 'success'" size="small">
              {{ row.order_type === 'activity' ? '活动订单' : '开卡单' }}
            </el-tag>
          </template>
        </el-table-column>
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
        <el-table-column prop="timeout_time" label="超时时间" width="170" />
        <el-table-column prop="end_time" label="结束时间" width="170" />
        <el-table-column label="操作" width="260" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openDetailDialog(row)">详情</el-button>
            <el-button link type="primary" @click="goReturnLogs(row)">回鱼流水</el-button>
            <el-button link type="primary" @click="goFishTrades(row)">卖鱼流水</el-button>
            <el-button
              v-if="row.status === 'ongoing' || row.status === 'timeout'"
              link
              type="danger"
              @click="finishSessionRow(row)"
            >手动结束</el-button>
            <el-button
              v-if="row.status === 'ongoing'"
              link
              type="warning"
              @click="cancelSessionRow(row)"
            >取消</el-button>
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
      v-model="detailDialogVisible"
      title="开钓单详情"
      width="620px"
      :close-on-click-modal="false"
    >
      <div v-loading="detailLoading">
        <el-descriptions v-if="detailData" :column="2" border>
          <el-descriptions-item label="开钓单号">{{ detailData.session_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(detailData.status)" size="small">{{ statusLabel(detailData.status) }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="用户">{{ detailData.user_nickname || '-' }}</el-descriptions-item>
          <el-descriptions-item label="类型">
            <el-tag :type="detailData.order_type === 'activity' ? 'warning' : 'success'" size="small">
              {{ detailData.order_type === 'activity' ? '活动订单' : '开卡单' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="钓场">{{ detailData.venue_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="池塘">{{ detailData.pond_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="钓位">
            <span v-if="detailData.seat_code">{{ detailData.seat_code }}</span>
            <span v-else-if="detailData.seat_no">#{{ detailData.seat_no }}</span>
            <span v-else>-</span>
          </el-descriptions-item>
          <el-descriptions-item label="下单套餐" :span="2">
            <span>{{ detailData.fee_rule_order_label || '-' }}</span>
            <span v-if="detailData.fee_rule_id" class="fee-rule-id">（规则ID {{ detailData.fee_rule_id }}）</span>
          </el-descriptions-item>
          <el-descriptions-item label="应收(元)">{{ formatMoney(detailData.amount_total_yuan) }}</el-descriptions-item>
          <el-descriptions-item label="实收(元)">{{ formatMoney(detailData.amount_paid_yuan) }}</el-descriptions-item>
          <el-descriptions-item label="押金(元)">{{ formatMoney(detailData.deposit_total_yuan) }}</el-descriptions-item>
          <el-descriptions-item label="开始时间">{{ detailData.start_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="超时时间">{{ detailData.timeout_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="已超时多久">{{ timeoutElapsedText }}</el-descriptions-item>
          <el-descriptions-item label="结束时间">{{ detailData.end_time || '-' }}</el-descriptions-item>
          <el-descriptions-item label="下单已多久">{{ formatElapsed(detailData.start_time, detailData.end_time) }}</el-descriptions-item>
          <el-descriptions-item label="备注" :span="2">{{ detailData.remark || '-' }}</el-descriptions-item>
        </el-descriptions>
        <el-empty v-else description="暂无详情" :image-size="80" />
      </div>
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

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
          <el-form-item label="小程序用户" prop="mini_user_id">
            <el-select
              v-model="createForm.mini_user_id"
              filterable
              remote
              reserve-keyword
              placeholder="搜索昵称 / openid / 手机号"
              :remote-method="onSearchMiniUser"
              :loading="searchingMiniUser"
              style="width: 100%"
            >
              <el-option
                v-for="u in miniUserOptions"
                :key="u.id"
                :value="String(u.id)"
                :label="formatMiniUserLabel(u)"
              >
                <div style="display:flex;align-items:center;gap:8px;">
                  <el-avatar :size="24" :src="formatStorageUrl(u.avatar)" />
                  <div style="display:flex;flex-direction:column;line-height:1.1;">
                    <div style="font-size:13px;">
                      <span>{{ u.nickname || '-' }}</span>
                      <el-tag v-if="Number(u.is_vip) === 1" type="warning" size="small" style="margin-left:6px;">会员</el-tag>
                    </div>
                    <div style="font-size:12px;color:#888;">
                      <span v-if="u.mobile">{{ u.mobile }}</span>
                      <span v-else-if="u.openid">{{ (u.openid || '').slice(0, 8) + '...' }}</span>
                    </div>
                  </div>
                </div>
              </el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="钓场" prop="venue_id">
            <el-select
              v-model="createForm.venue_id"
              placeholder="请选择钓场"
              style="width:100%"
              :disabled="!!venueStore.venueId"
              @change="onCreateVenueChange"
            >
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
              <el-option
                v-for="s in seatOptions"
                :key="s.id"
                :value="String(s.id)"
                :disabled="!!s.occupied"
                :label="`#${s.seat_no} (${s.code || ''})${s.occupied ? '（已占用）' : ''}`"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="收费规则" prop="fee_rule_id">
            <el-select v-model="createForm.fee_rule_id" placeholder="请选择（正钓/偷驴）" style="width:100%">
              <el-option
                v-for="f in feeRuleOptions"
                :key="f.id"
                :label="`${f.name}（${f.duration || ''} ￥${f.amount}）`"
                :value="String(f.id)"
              />
            </el-select>
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
        <el-button
          type="primary"
          :loading="createSubmitting"
          @click="submitCreate('trial')"
        >
          生成测试版二维码
        </el-button>
        <el-button
          type="success"
          :loading="createSubmitting"
          @click="submitCreate('release')"
        >
          生成正式版二维码
        </el-button>
      </template>
    </el-dialog>
    <el-dialog
    v-model="payQrDialogVisible"
    title="扫码支付"
    width="360px"
    :close-on-click-modal="false"
  >
    <div style="text-align: center;">
      <div style="margin-bottom: 8px;">待支付金额：<strong>{{ formatMoney(payQrInfo.needPay) }}</strong> 元</div>
      <div v-if="payQrInfo.miniQrUrl">
        <img :src="payQrInfo.miniQrUrl" alt="小程序码" style="width: 260px; height: 260px;" />
      </div>
      <div v-else style="font-size: 12px; color: #888;">
        小程序码生成失败，请使用路径：{{ payQrInfo.miniPayPath }}
      </div>
    </div>
  </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getSessionList, getSessionDetail, getServerNow, createSession, finishSession, cancelSession } from '@/api/session'
import { getVenueOptions, getPondList, getPondSeats, getPondFeeRules } from '@/api/pond'
import { searchMiniUsers } from '@/api/miniUser'
import { useVenueContextStore } from '@/stores/venueContext'

const router = useRouter()
const route = useRoute()
const venueStore = useVenueContextStore()
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
const detailDialogVisible = ref(false)
const detailLoading = ref(false)
const detailData = ref(null)
const calibratedServerNowMs = ref(0)
const calibratedLocalBaseMs = ref(0)
const nowTick = ref(0)
let timeoutTickTimer = null
let timeoutResyncTimer = null
const createFormRef = ref(null)
const createSubmitting = ref(false)
const payQrDialogVisible = ref(false)
const payQrInfo = reactive({
  needPay: 0,
  miniPayPath: '',
  miniQrUrl: '',
})
const createForm = reactive({
  mini_user_id: '',
  venue_id: '',
  pond_id: '',
  seat_id: '',
  fee_rule_id: '',
  use_balance: true,
  remark: '',
})
const createRules = {
  mini_user_id: [{ required: true, message: '请选择小程序用户', trigger: 'change' }],
  venue_id: [{ required: true, message: '请选择钓场', trigger: 'change' }],
  pond_id: [{ required: true, message: '请选择池塘', trigger: 'change' }],
  fee_rule_id: [{ required: true, message: '请选择收费规则', trigger: 'change' }],
}

const venueOptions = ref([])
const pondOptions = ref([])
const seatOptions = ref([])
const feeRuleOptions = ref([])
const miniUserOptions = ref([])
const searchingMiniUser = ref(false)

function statusLabel(status) {
  const map = { ongoing: '进行中', timeout: '已超时', finished: '已结束', settled: '已结算', cancelled: '已取消' }
  return map[status] || status || '-'
}

function statusTagType(status) {
  const map = { ongoing: 'success', timeout: 'danger', finished: 'info', settled: 'warning', cancelled: 'info' }
  return map[status] || 'info'
}

function formatMoney(v) {
  const n = Number(v || 0)
  return n.toFixed(2)
}

function formatElapsed(startTime, endTime) {
  if (!startTime) return '-'
  const startTs = new Date(startTime).getTime()
  if (!Number.isFinite(startTs) || startTs <= 0) return '-'
  const endTs = endTime ? new Date(endTime).getTime() : Date.now()
  if (!Number.isFinite(endTs) || endTs < startTs) return '-'
  let seconds = Math.floor((endTs - startTs) / 1000)
  const days = Math.floor(seconds / 86400)
  seconds -= days * 86400
  const hours = Math.floor(seconds / 3600)
  seconds -= hours * 3600
  const minutes = Math.floor(seconds / 60)
  seconds -= minutes * 60
  const parts = []
  if (days > 0) parts.push(`${days}天`)
  if (hours > 0) parts.push(`${hours}小时`)
  if (minutes > 0) parts.push(`${minutes}分`)
  if (parts.length === 0) parts.push(`${seconds}秒`)
  return parts.join('')
}

function formatElapsedSeconds(secondsTotal) {
  if (!Number.isFinite(secondsTotal) || secondsTotal < 0) return '-'
  let seconds = Math.floor(secondsTotal)
  const days = Math.floor(seconds / 86400)
  seconds -= days * 86400
  const hours = Math.floor(seconds / 3600)
  seconds -= hours * 3600
  const minutes = Math.floor(seconds / 60)
  seconds -= minutes * 60
  const parts = []
  if (days > 0) parts.push(`${days}天`)
  if (hours > 0) parts.push(`${hours}小时`)
  if (minutes > 0) parts.push(`${minutes}分`)
  if (parts.length === 0) parts.push(`${seconds}秒`)
  return parts.join('')
}

const calibratedNowMs = computed(() => {
  void nowTick.value
  if (calibratedServerNowMs.value <= 0 || calibratedLocalBaseMs.value <= 0) {
    return Date.now()
  }
  return calibratedServerNowMs.value + (Date.now() - calibratedLocalBaseMs.value)
})

const timeoutElapsedText = computed(() => {
  const d = detailData.value
  if (!d || d.status !== 'timeout' || !d.timeout_time) return '-'
  const timeoutTs = new Date(d.timeout_time).getTime()
  if (!Number.isFinite(timeoutTs) || timeoutTs <= 0) return '-'
  const endTs = d.end_time ? new Date(d.end_time).getTime() : 0
  const nowTs = Number.isFinite(endTs) && endTs > 0 ? endTs : calibratedNowMs.value
  if (!Number.isFinite(nowTs) || nowTs < timeoutTs) return '-'
  return formatElapsedSeconds((nowTs - timeoutTs) / 1000)
})

function applyServerNow(serverNow) {
  const serverMs = new Date(serverNow || '').getTime()
  if (!Number.isFinite(serverMs) || serverMs <= 0) return
  calibratedServerNowMs.value = serverMs
  calibratedLocalBaseMs.value = Date.now()
}

function stopTimeoutAutoTick() {
  if (timeoutTickTimer) {
    clearInterval(timeoutTickTimer)
    timeoutTickTimer = null
  }
  if (timeoutResyncTimer) {
    clearInterval(timeoutResyncTimer)
    timeoutResyncTimer = null
  }
}

function startTimeoutAutoTick() {
  stopTimeoutAutoTick()
  // 每秒触发一次响应式重算
  timeoutTickTimer = setInterval(() => {
    nowTick.value += 1
  }, 1000)
  // 每 45 秒向服务端校准一次当前时间（轻接口，不查业务详情）
  timeoutResyncTimer = setInterval(async () => {
    try {
      if (!detailDialogVisible.value) return
      const res = await getServerNow()
      const d = res?.data ?? res
      if (d?.server_now) {
        applyServerNow(d.server_now)
      }
    } catch (_) {}
  }, 45000)
}

async function openDetailDialog(row) {
  detailDialogVisible.value = true
  detailLoading.value = true
  try {
    const res = await getSessionDetail(row.id)
    const d = res?.data ?? res
    detailData.value = {
      ...row,
      ...(d || {}),
    }
    applyServerNow(detailData.value?.server_now)
    if (detailData.value?.status === 'timeout' && !detailData.value?.end_time) {
      startTimeoutAutoTick()
    } else {
      stopTimeoutAutoTick()
    }
  } catch (e) {
    detailData.value = row || null
    stopTimeoutAutoTick()
  } finally {
    detailLoading.value = false
  }
}

watch(detailDialogVisible, (open) => {
  if (!open) {
    stopTimeoutAutoTick()
    return
  }
  if (detailData.value?.status === 'timeout' && !detailData.value?.end_time) {
    startTimeoutAutoTick()
  }
})

async function fetchList() {
  loading.value = true
  try {
    const res = await getSessionList({
      page: page.value,
      limit: limit.value,
      session_no: filters.session_no || undefined,
      status: filters.status || undefined,
      seat_code: filters.seat_code || undefined,
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
  filters.session_no = ''
  filters.status = ''
  filters.seat_code = ''
  page.value = 1
  fetchList()
}

function goReturnLogs(row) {
  router.push({
    path: '/return-logs',
    query: {
      session_id: row.id,
      pond_id: row.pond_id || '',
    },
  })
}

function goFishTrades(row) {
  router.push({
    path: '/fish-trades',
    query: {
      session_id: row.id,
      pond_id: row.pond_id || '',
      venue_id: row.venue_id || '',
    },
  })
}

async function finishSessionRow(row) {
  try {
    await ElMessageBox.confirm(`确认结束开钓单「${row.session_no}」吗？`, '提示', {
      type: 'warning',
    })
  } catch {
    return
  }
  try {
    await finishSession(row.id)
    ElMessage.success('开钓单已结束')
    fetchList()
  } catch (e) {
    console.error(e)
  }
}

async function cancelSessionRow(row) {
  try {
    await ElMessageBox.confirm(`确认取消开钓单「${row.session_no}」吗？`, '提示', {
      type: 'warning',
    })
  } catch {
    return
  }
  try {
    await cancelSession(row.id)
    ElMessage.success('开钓单已取消')
    fetchList()
  } catch (e) {
    console.error(e)
  }
}

async function openCreateDialog() {
  if (!venueOptions.value.length) {
    await loadVenues()
  }
  createForm.mini_user_id = ''
  createForm.venue_id = venueStore.venueId ? String(venueStore.venueId) : ''
  createForm.pond_id = ''
  createForm.seat_id = ''
  createForm.fee_rule_id = ''
  createForm.use_balance = true
  createForm.remark = ''
  pondOptions.value = []
  seatOptions.value = []
  feeRuleOptions.value = []
  miniUserOptions.value = []
  if (createForm.venue_id) {
    onCreateVenueChange()
  }
  createDialogVisible.value = true
}

function resetCreateForm() {
  if (createFormRef.value) createFormRef.value.clearValidate()
  createForm.mini_user_id = ''
  createForm.venue_id = ''
  createForm.pond_id = ''
  createForm.seat_id = ''
  createForm.fee_rule_id = ''
  createForm.use_balance = true
  createForm.remark = ''
  pondOptions.value = []
  seatOptions.value = []
  feeRuleOptions.value = []
  miniUserOptions.value = []
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

async function submitCreate(env) {
  if (!createFormRef.value) return
  await createFormRef.value.validate()
  try {
    createSubmitting.value = true
    const payload = {
      mini_user_id: Number(createForm.mini_user_id),
      venue_id: Number(createForm.venue_id),
      pond_id: Number(createForm.pond_id),
      seat_id: createForm.seat_id ? Number(createForm.seat_id) : undefined,
      fee_rule_id: Number(createForm.fee_rule_id),
      use_balance: createForm.use_balance,
      qr_env: env === 'release' ? 'release' : 'trial',
      remark: createForm.remark || '',
    }
    const res = await createSession(payload)
    const data = res?.data ?? res
    const miniPayPath = data?.mini_pay_path
    const needPay = data?.need_pay ?? 0
    const deducted = data?.balance_deduct ?? 0
    const miniQrUrl = data?.mini_qr_url || ''
    const feeAmount = data?.fee_amount ?? null
    const feeDeposit = data?.fee_deposit ?? null
    const depositEffective = data?.deposit_effective ?? null
    const depositWaived = !!data?.deposit_waived

    let msg = `开钓订单创建成功。余额抵扣：${deducted} 元，待支付：${needPay} 元`
    if (feeAmount != null && feeDeposit != null) {
      msg += `\n钓费：${feeAmount} 元，押金：${feeDeposit} 元`
    }
    if (depositWaived) {
      msg += `（会员免押金，本次实际收取押金 ${depositEffective ?? 0} 元）`
    }

    ElMessage.success(msg)

    // 弹出支付二维码对话框（如果生成成功）
    if (needPay > 0 && (miniQrUrl || miniPayPath)) {
      payQrInfo.needPay = needPay
      payQrInfo.miniPayPath = miniPayPath || ''
      payQrInfo.miniQrUrl = miniQrUrl || ''
      payQrDialogVisible.value = true
    }

    createDialogVisible.value = false
    fetchList()
  } catch (e) {
    console.error(e)
  } finally {
    createSubmitting.value = false
  }
}

watch(
  () => venueStore.venueId,
  () => {
    page.value = 1
    fetchList()
  }
)

watch(
  () => route.query.status,
  (v) => {
    filters.status = typeof v === 'string' ? v : ''
    page.value = 1
    fetchList()
  }
)

onMounted(() => {
  if (typeof route.query.status === 'string' && route.query.status) {
    filters.status = route.query.status
  }
  fetchList()
  loadVenues()
})

onUnmounted(() => {
  stopTimeoutAutoTick()
})

function formatMiniUserLabel(u) {
  const parts = []
  if (u.nickname) parts.push(u.nickname)
  if (u.mobile) parts.push(u.mobile)
  if (u.openid) parts.push(u.openid.slice(0, 8) + '...')
  return parts.join(' / ')
}

function formatStorageUrl(u) {
  if (!u) return ''
  if (u.startsWith('http')) return u
  const base = import.meta.env.VITE_STORAGE_URL || ''
  return base + u
}

async function onSearchMiniUser(query) {
  if (!query) {
    miniUserOptions.value = []
    return
  }
  searchingMiniUser.value = true
  try {
    const res = await searchMiniUsers({ keyword: query, limit: 20 })
    const data = res?.data ?? res
    miniUserOptions.value = data?.list ?? []
  } finally {
    searchingMiniUser.value = false
  }
}
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
.fee-rule-id {
  margin-left: 6px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
</style>

