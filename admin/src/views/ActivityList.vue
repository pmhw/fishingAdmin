<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>经营管理 - 活动</span>
          <el-button type="primary" @click="openEdit()">新建活动</el-button>
        </div>
      </template>

      <el-alert
        class="page-tip"
        type="info"
        :closable="false"
        show-icon
        title="发布中的活动会禁止小程序对该池塘「开钓单」；结束活动后恢复。请先配置活动收费规则再发布。"
      />

      <el-form inline class="filter-form">
        <el-form-item label="状态">
          <el-select v-model="filterStatus" placeholder="全部" clearable style="width: 140px" @change="fetchList">
            <el-option label="草稿" value="draft" />
            <el-option label="发布中" value="published" />
            <el-option label="已结束" value="closed" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">刷新</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="活动名" min-width="140" show-overflow-tooltip />
        <el-table-column label="池塘" width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ pondNameMap[row.pond_id] || `#${row.pond_id}` }}</template>
        </el-table-column>
        <el-table-column label="名额" width="90">
          <template #default="{ row }">
            {{ row.participant_count > 0 ? row.participant_count : '不限' }}
          </template>
        </el-table-column>
        <el-table-column label="抽号" width="120">
          <template #default="{ row }">
            <span>{{ drawModeLabel(row.draw_mode) }}</span>
            <el-tag
              v-if="row.draw_mode === 'unified' && row.unified_draw_enabled"
              type="success"
              size="small"
              class="tag-inline"
            >已开抽</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="open_time" label="开钓时间" width="165" />
        <el-table-column prop="register_deadline" label="报名截止" width="165" />
        <el-table-column label="1元积分" width="88" align="center">
          <template #default="{ row }">
            {{ Number(row.points_divisor) > 0 ? row.points_divisor : '不发' }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" fixed="right" width="320">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="primary" @click="openFeeRules(row)">收费规则</el-button>
            <el-button
              v-if="row.status === 'draft'"
              link
              type="success"
              @click="onPublish(row)"
            >发布</el-button>
            <el-button
              v-if="row.status === 'published' && row.draw_mode === 'unified' && !row.unified_draw_enabled"
              link
              type="warning"
              @click="onUnifiedStart(row)"
            >开启统一抽号</el-button>
            <el-button
              v-if="row.status === 'published'"
              link
              type="danger"
              @click="onClose(row)"
            >结束活动</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 新建/编辑活动 -->
    <el-dialog
      v-model="editVisible"
      :title="editId ? '编辑活动' : '新建活动'"
      width="640px"
      destroy-on-close
      :close-on-click-modal="false"
      @close="resetEditForm"
    >
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="120px">
        <el-form-item label="活动名称" prop="name">
          <el-input v-model="editForm.name" placeholder="必填" />
        </el-form-item>
        <el-form-item label="活动池塘" prop="pond_id">
          <el-select
            v-model="editForm.pond_id"
            placeholder="请选择池塘"
            filterable
            style="width: 100%"
            :disabled="!!editId"
          >
            <el-option
              v-for="p in pondOptions"
              :key="p.id"
              :label="`${p.name}（${p.venue_name || ''}）`"
              :value="p.id"
            />
          </el-select>
          <p v-if="!editId" class="form-hint">保存后不可修改池塘；新活动建议使用专用新池塘。</p>
        </el-form-item>
        <el-form-item label="参与人数上限">
          <el-input-number v-model="editForm.participant_count" :min="0" :max="99999" controls-position="right" />
          <span class="form-hint-inline">0 表示不限</span>
        </el-form-item>
        <el-form-item label="开钓时间" prop="open_time">
          <el-date-picker
            v-model="editForm.open_time"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="选择开钓时间"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="报名截止" prop="register_deadline">
          <el-date-picker
            v-model="editForm.register_deadline"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="报名截止时间"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="抽号方式" prop="draw_mode">
          <el-select v-model="editForm.draw_mode" style="width: 100%">
            <el-option label="线上随机（付后即分配）" value="random" />
            <el-option label="线上自选号码" value="self_pick" />
            <el-option label="线上统一抽号（管理员开启后用户点按钮）" value="unified" />
            <el-option label="线下现场抽号" value="offline" />
          </el-select>
        </el-form-item>
        <el-form-item label="1元积分">
          <el-input-number v-model="editForm.points_divisor" :min="0" :max="999999" controls-position="right" />
          <span class="form-hint-inline">每实付 1 元可得多少积分（例：10 即 1 元=10 积分）。填 0 表示不发放积分。</span>
        </el-form-item>
        <el-form-item label="活动描述">
          <el-input v-model="editForm.description" type="textarea" :rows="4" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="editSubmitting" @click="submitEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 收费规则 -->
    <el-dialog
      v-model="feeVisible"
      title="活动收费规则"
      width="720px"
      destroy-on-close
      :close-on-click-modal="false"
      @opened="onFeeDialogOpen"
    >
      <div v-loading="feeLoading" class="fee-wrap">
        <p class="fee-title">已配置规则（小程序报名可选）</p>
        <el-table :data="feeRules" stripe size="small" max-height="240">
          <el-table-column prop="name" label="名称" min-width="100" />
          <el-table-column prop="duration" label="时长" width="100" />
          <el-table-column label="金额(元)" width="100">
            <template #default="{ row }">{{ row.amount }}</template>
          </el-table-column>
          <el-table-column label="押金(元)" width="100">
            <template #default="{ row }">{{ row.deposit }}</template>
          </el-table-column>
        </el-table>

        <el-divider>新增一条</el-divider>
        <el-form ref="feeFormRef" :model="feeForm" :rules="feeRulesValid" label-width="100px" class="fee-add-form">
          <el-form-item label="收费名称" prop="name">
            <el-input v-model="feeForm.name" placeholder="如 正钓 4 小时" />
          </el-form-item>
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="时长数值" prop="duration_value">
                <el-input-number v-model="feeForm.duration_value" :min="0" :precision="2" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="单位" prop="duration_unit">
                <el-select v-model="feeForm.duration_unit" style="width: 100%">
                  <el-option label="小时" value="hour" />
                  <el-option label="天" value="day" />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="12">
            <el-col :span="12">
              <el-form-item label="收费(元)" prop="amount">
                <el-input-number v-model="feeForm.amount" :min="0" :precision="2" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="押金(元)">
                <el-input-number v-model="feeForm.deposit" :min="0" :precision="2" controls-position="right" style="width: 100%" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="排序">
            <el-input-number v-model="feeForm.sort_order" :min="0" controls-position="right" />
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :loading="feeAdding" @click="submitFeeRule">添加规则</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { ElMessageBox } from 'element-plus'
import {
  getActivityList,
  getActivityDetail,
  createActivity,
  updateActivity,
  publishActivity,
  closeActivity,
  createActivityFeeRule,
  unifiedDrawStart,
} from '@/api/activity'
import { getPondList } from '@/api/pond'
import { useVenueContextStore } from '@/stores/venueContext'

const venueStore = useVenueContextStore()

const loading = ref(false)
const list = ref([])
const filterStatus = ref('')

const pondOptions = ref([])
const pondNameMap = computed(() => {
  const m = {}
  for (const p of pondOptions.value) {
    m[p.id] = p.name
  }
  return m
})

async function loadPonds() {
  const params = { page: 1, limit: 500 }
  if (venueStore.venueId) params.venue_id = venueStore.venueId
  try {
    const res = await getPondList(params)
    const data = res?.data ?? res
    pondOptions.value = data?.list ?? []
  } catch {
    pondOptions.value = []
  }
}

watch(
  () => venueStore.venueId,
  () => {
    loadPonds()
  }
)

function drawModeLabel(v) {
  const map = {
    random: '线上随机',
    self_pick: '线上自选',
    unified: '统一抽号',
    offline: '线下现场',
  }
  return map[v] || v || '-'
}

function statusLabel(s) {
  const map = { draft: '草稿', published: '发布中', closed: '已结束' }
  return map[s] || s || '-'
}

function statusTag(s) {
  if (s === 'published') return 'success'
  if (s === 'closed') return 'info'
  return ''
}

async function fetchList() {
  loading.value = true
  try {
    const params = {}
    if (filterStatus.value) params.status = filterStatus.value
    const res = await getActivityList(params)
    const data = res?.data ?? res
    list.value = data?.list ?? []
  } catch {
    list.value = []
  } finally {
    loading.value = false
  }
}

const editVisible = ref(false)
const editId = ref(null)
const editSubmitting = ref(false)
const editFormRef = ref(null)
const editForm = reactive({
  name: '',
  pond_id: undefined,
  participant_count: 0,
  open_time: '',
  register_deadline: '',
  description: '',
  draw_mode: 'random',
  points_divisor: 1,
})

const editRules = {
  name: [{ required: true, message: '请输入活动名', trigger: 'blur' }],
  pond_id: [{ required: true, message: '请选择池塘', trigger: 'change' }],
  open_time: [{ required: true, message: '请选择开钓时间', trigger: 'change' }],
  register_deadline: [{ required: true, message: '请选择报名截止时间', trigger: 'change' }],
  draw_mode: [{ required: true, message: '请选择抽号方式', trigger: 'change' }],
}

function resetEditForm() {
  editId.value = null
  Object.assign(editForm, {
    name: '',
    pond_id: undefined,
    participant_count: 0,
    open_time: '',
    register_deadline: '',
    description: '',
    draw_mode: 'random',
    points_divisor: 1,
  })
  editFormRef.value?.resetFields?.()
}

function openEdit(row) {
  resetEditForm()
  if (row) {
    editId.value = row.id
    editForm.name = row.name || ''
    editForm.pond_id = row.pond_id
    editForm.participant_count = row.participant_count ?? 0
    editForm.open_time = row.open_time || ''
    editForm.register_deadline = row.register_deadline || ''
    editForm.description = row.description || ''
    editForm.draw_mode = row.draw_mode || 'random'
    editForm.points_divisor = row.points_divisor ?? 1
  }
  editVisible.value = true
}

async function submitEdit() {
  await editFormRef.value?.validate?.().catch(() => {
    throw new Error('validate')
  })
  editSubmitting.value = true
  try {
    if (editId.value) {
      await updateActivity(editId.value, {
        name: editForm.name,
        participant_count: editForm.participant_count,
        open_time: editForm.open_time,
        register_deadline: editForm.register_deadline,
        description: editForm.description,
        draw_mode: editForm.draw_mode,
        points_divisor: editForm.points_divisor,
      })
    } else {
      await createActivity({
        name: editForm.name,
        pond_id: editForm.pond_id,
        participant_count: editForm.participant_count,
        open_time: editForm.open_time,
        register_deadline: editForm.register_deadline,
        description: editForm.description,
        draw_mode: editForm.draw_mode,
        points_divisor: editForm.points_divisor,
      })
    }
    editVisible.value = false
    await fetchList()
  } catch (e) {
    if (e?.message === 'validate') return
  } finally {
    editSubmitting.value = false
  }
}

async function onPublish(row) {
  try {
    await ElMessageBox.confirm('发布后该池塘小程序将无法再开普通开钓单，确定发布？', '发布活动', {
      type: 'warning',
    })
  } catch {
    return
  }
  try {
    await publishActivity(row.id)
    await fetchList()
  } catch {
    /* ElMessage */
  }
}

async function onClose(row) {
  try {
    await ElMessageBox.confirm('结束后用户可再次对该池塘开钓单（普通开卡）。确定结束？', '结束活动', {
      type: 'warning',
    })
  } catch {
    return
  }
  try {
    await closeActivity(row.id)
    await fetchList()
  } catch {
    /* */
  }
}

async function onUnifiedStart(row) {
  try {
    await ElMessageBox.confirm('开启后，已付费用户可在小程序活动页点击抽号。确定开启？', '统一抽号', { type: 'info' })
  } catch {
    return
  }
  try {
    await unifiedDrawStart(row.id)
    await fetchList()
  } catch {
    /* */
  }
}

/** 收费规则弹窗 */
const feeVisible = ref(false)
const feeActivityId = ref(null)
const feeLoading = ref(false)
const feeAdding = ref(false)
const feeRules = ref([])
const feeFormRef = ref(null)
const feeForm = reactive({
  name: '',
  duration_value: 4,
  duration_unit: 'hour',
  amount: 0,
  deposit: 0,
  sort_order: 0,
})
const feeRulesValid = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  duration_unit: [{ required: true, message: '请选择单位', trigger: 'change' }],
}

function openFeeRules(row) {
  feeActivityId.value = row.id
  feeVisible.value = true
}

async function onFeeDialogOpen() {
  if (!feeActivityId.value) return
  feeLoading.value = true
  try {
    const res = await getActivityDetail(feeActivityId.value)
    const data = res?.data ?? res
    feeRules.value = data?.fee_rules ?? []
  } catch {
    feeRules.value = []
  } finally {
    feeLoading.value = false
  }
  Object.assign(feeForm, {
    name: '',
    duration_value: 4,
    duration_unit: 'hour',
    amount: 0,
    deposit: 0,
    sort_order: 0,
  })
}

async function submitFeeRule() {
  await feeFormRef.value?.validate?.().catch(() => {
    throw new Error('v')
  })
  feeAdding.value = true
  try {
    await createActivityFeeRule(feeActivityId.value, { ...feeForm })
    const res = await getActivityDetail(feeActivityId.value)
    const data = res?.data ?? res
    feeRules.value = data?.fee_rules ?? []
    feeForm.name = ''
    feeFormRef.value?.clearValidate?.()
  } catch (e) {
    if (e?.message === 'v') return
  } finally {
    feeAdding.value = false
  }
}

onMounted(() => {
  loadPonds()
  fetchList()
})
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.filter-form {
  margin-bottom: 12px;
}
.page-tip {
  margin-bottom: 16px;
}
.form-hint {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  line-height: 1.4;
}
.form-hint-inline {
  margin-left: 8px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
.tag-inline {
  margin-left: 6px;
  vertical-align: middle;
}
.fee-title {
  margin: 0 0 8px;
  font-size: 14px;
  font-weight: 600;
}
.fee-add-form {
  max-width: 520px;
}
</style>
