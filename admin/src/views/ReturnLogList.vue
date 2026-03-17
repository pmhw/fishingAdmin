<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>经营管理 - 回鱼流水</span>
          <el-button type="primary" @click="openForm()">新增</el-button>
        </div>
      </template>

      <el-form inline class="filter-form">
        <el-form-item label="开钓单ID">
          <el-input v-model="filters.session_id" placeholder="session_id" clearable style="width: 160px" />
        </el-form-item>
        <el-form-item label="池塘ID">
          <el-input v-model="filters.pond_id" placeholder="pond_id" clearable style="width: 140px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchList">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="session_id" label="开钓单" width="90" />
        <el-table-column prop="pond_id" label="池塘" width="80" />
        <el-table-column label="方式" width="90">
          <template #default="{ row }">{{ row.return_type === 'tiao' ? '按条' : '按斤' }}</template>
        </el-table-column>
        <el-table-column label="数量" width="140">
          <template #default="{ row }">
            {{ row.qty }} {{ row.return_type === 'tiao' ? '条' : '斤' }}
          </template>
        </el-table-column>
        <el-table-column label="单价" width="150">
          <template #default="{ row }">
            {{ row.unit_price }} 元/{{ row.return_type === 'tiao' ? '条' : '斤' }}
          </template>
        </el-table-column>
        <el-table-column prop="amount" label="金额(元)" width="110" />
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openForm(row)">编辑</el-button>
            <el-button link type="danger" size="small" @click="onDelete(row)">删除</el-button>
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
      v-model="dialogVisible"
      :title="editId ? '编辑回鱼流水' : '新增回鱼流水'"
      width="520px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetForm"
    >
      <div v-loading="submitLoading" element-loading-text="提交中…">
        <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
          <el-form-item label="开钓单" prop="session_id">
            <el-input v-model="form.session_id" placeholder="必填 session_id" />
          </el-form-item>
          <el-form-item label="方式" prop="return_type">
            <el-select v-model="form.return_type" style="width:100%">
              <el-option label="按斤" value="jin" />
              <el-option label="按条" value="tiao" />
            </el-select>
          </el-form-item>
          <el-form-item :label="qtyLabel" prop="qty">
            <el-input-number v-model="form.qty" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>
          <el-form-item :label="priceLabel" prop="unit_price">
            <el-input-number v-model="form.unit_price" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>
          <el-form-item label="金额(自动计算)" prop="amount">
            <el-input-number v-model="form.amount" :min="0" :precision="2" controls-position="right" style="width:100%" disabled />
          </el-form-item>
          <div class="hint-text">
            {{ form.return_type === 'tiao'
              ? '按条：数量=条数，单价=元/条，金额=数量×单价；系统会根据池塘已配置的回鱼规则自动选择合适的单价。'
              : '按斤：数量=重量(斤)，单价=元/斤，金额=数量×单价；系统会根据池塘已配置的回鱼规则自动选择合适的单价。' }}
          </div>
          <el-form-item label="备注">
            <el-input v-model="form.remark" placeholder="选填" />
          </el-form-item>
        </el-form>
      </div>
      <template #footer>
        <el-button :disabled="submitLoading" @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getReturnLogList, createReturnLog, updateReturnLog, deleteReturnLog } from '@/api/returnLog'

const route = useRoute()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const filters = reactive({
  session_id: route.query.session_id ? String(route.query.session_id) : '',
  pond_id: route.query.pond_id ? String(route.query.pond_id) : '',
})

const dialogVisible = ref(false)
const editId = ref(null)
const formRef = ref(null)
const submitLoading = ref(false)
const form = reactive({
  session_id: '',
  return_type: 'jin',
  qty: 0,
  unit_price: 0,
  amount: 0,
  remark: '',
})
const rules = {
  session_id: [{ required: true, message: '请输入 session_id', trigger: 'blur' }],
}

const qtyLabel = computed(() => (form.return_type === 'tiao' ? '数量（条）' : '数量（斤）'))
const priceLabel = computed(() => (form.return_type === 'tiao' ? '单价（元/条）' : '单价（元/斤）'))

watch(
  () => [form.qty, form.unit_price, form.return_type],
  () => {
    const q = Number(form.qty || 0)
    const p = Number(form.unit_price || 0)
    form.amount = Number.isFinite(q * p) ? Number((q * p).toFixed(2)) : 0
  },
  { immediate: true }
)

async function fetchList() {
  loading.value = true
  try {
    const res = await getReturnLogList({
      page: page.value,
      limit: limit.value,
      session_id: filters.session_id || undefined,
      pond_id: filters.pond_id || undefined,
    })
    const data = res?.data ?? res
    list.value = data?.list ?? []
    total.value = data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.session_id = ''
  filters.pond_id = ''
  page.value = 1
  fetchList()
}

function openForm(row) {
  if (row) {
    editId.value = row.id
    form.session_id = String(row.session_id || '')
    form.return_type = row.return_type || 'jin'
    form.qty = Number(row.qty || 0)
    form.unit_price = Number(row.unit_price || 0)
    form.amount = Number(row.amount || 0)
    form.remark = row.remark || ''
  } else {
    editId.value = null
    form.session_id = filters.session_id || ''
    form.return_type = 'jin'
    form.qty = 0
    form.unit_price = 0
    form.amount = 0
    form.remark = ''
  }
  dialogVisible.value = true
}

function resetForm() {
  dialogVisible.value = false
  editId.value = null
  if (formRef.value) formRef.value.clearValidate()
}

async function submit() {
  if (!formRef.value) return
  await formRef.value.validate()
  try {
    submitLoading.value = true
    const payload = {
      session_id: Number(form.session_id),
      return_type: form.return_type,
      qty: form.qty,
      unit_price: form.unit_price,
      amount: form.amount,
      remark: form.remark,
    }
    if (editId.value) {
      await updateReturnLog(editId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createReturnLog(payload)
      ElMessage.success('添加成功')
    }
    dialogVisible.value = false
    fetchList()
  } finally {
    submitLoading.value = false
  }
}

async function onDelete(row) {
  try {
    await ElMessageBox.confirm('确定删除该回鱼流水？', '提示', { type: 'warning' })
  } catch {
    return
  }
  await deleteReturnLog(row.id)
  ElMessage.success('删除成功')
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
</style>

