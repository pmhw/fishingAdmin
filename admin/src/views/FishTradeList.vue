<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>经营管理 - 卖鱼/收鱼流水</span>
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
        <el-form-item label="类型">
          <el-select v-model="filters.trade_type" placeholder="全部" clearable style="width: 160px">
            <el-option label="卖入/收鱼" value="buy_in" />
            <el-option label="卖出/卖鱼" value="sell_out" />
          </el-select>
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
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="row.trade_type === 'sell_out' ? 'success' : 'info'" size="small">
              {{ row.trade_type === 'sell_out' ? '卖出' : '卖入' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="单位" width="80">
          <template #default="{ row }">{{ row.unit === 'tiao' ? '条' : '斤' }}</template>
        </el-table-column>
        <el-table-column prop="qty" label="数量" width="110" />
        <el-table-column prop="unit_price" label="单价" width="110" />
        <el-table-column prop="amount" label="金额(元)" width="110" />
        <el-table-column label="凭证" width="120">
          <template #default="{ row }">
            <el-image
              v-if="row.images && row.images.length"
              :src="formatStorageUrl(row.images[0])"
              :preview-src-list="row.images.map(formatStorageUrl)"
              preview-teleported
              fit="cover"
              style="width:60px;height:60px;border-radius:4px"
            />
            <span v-else style="color:#999">-</span>
          </template>
        </el-table-column>
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
      :title="editId ? '编辑交易流水' : '新增交易流水'"
      width="560px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="resetForm"
    >
      <div v-loading="submitLoading" element-loading-text="提交中…">
        <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
          <el-form-item label="钓场ID" prop="venue_id">
            <el-input v-model="form.venue_id" placeholder="必填" />
          </el-form-item>
          <el-form-item label="池塘ID">
            <el-input v-model="form.pond_id" placeholder="选填（建议填）" />
          </el-form-item>
          <el-form-item label="开钓单">
            <el-input v-model="form.session_id" placeholder="选填：关联开钓单" />
          </el-form-item>
          <el-form-item label="类型" prop="trade_type">
            <el-select v-model="form.trade_type" style="width:100%">
              <el-option label="卖入/收鱼" value="buy_in" />
              <el-option label="卖出/卖鱼" value="sell_out" />
            </el-select>
          </el-form-item>
          <el-form-item label="单位" prop="unit">
            <el-select v-model="form.unit" style="width:100%">
              <el-option label="斤" value="jin" />
              <el-option label="条" value="tiao" />
            </el-select>
          </el-form-item>
          <el-form-item label="数量" prop="qty">
            <el-input-number v-model="form.qty" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>
          <el-form-item label="单价" prop="unit_price">
            <el-input-number v-model="form.unit_price" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>
          <el-form-item label="金额" prop="amount">
            <el-input-number v-model="form.amount" :min="0" :precision="2" controls-position="right" style="width:100%" />
          </el-form-item>

          <el-form-item label="凭证图片">
            <div class="upload-multi-wrap">
              <div class="thumb-list">
                <div v-for="(img, idx) in form.images" :key="idx" class="thumb-item">
                  <el-image
                    :src="img.preview || formatStorageUrl(img.url)"
                    :preview-src-list="form.images.map((i) => i.preview || formatStorageUrl(i.url))"
                    preview-teleported
                    fit="cover"
                    style="width:86px;height:86px;border-radius:4px"
                  />
                  <span class="thumb-remove" @click="removeImage(idx)">×</span>
                </div>
                <el-upload
                  v-if="form.images.length < 9"
                  class="thumb-item upload-add-card"
                  :show-file-list="false"
                  accept="image/*"
                  :auto-upload="false"
                  :on-change="handleFileChange"
                >
                  <div class="upload-add-inner">
                    <span class="upload-add-icon">＋</span>
                    <span class="upload-add-text">添加图片</span>
                  </div>
                </el-upload>
              </div>
            </div>
          </el-form-item>

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
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getFishTradeList, createFishTrade, updateFishTrade, deleteFishTrade } from '@/api/fishTrade'
import { uploadImage } from '@/api/pond'

const route = useRoute()
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const filters = reactive({
  session_id: route.query.session_id ? String(route.query.session_id) : '',
  pond_id: route.query.pond_id ? String(route.query.pond_id) : '',
  trade_type: '',
})

const dialogVisible = ref(false)
const editId = ref(null)
const formRef = ref(null)
const submitLoading = ref(false)
const form = reactive({
  venue_id: '',
  pond_id: '',
  session_id: '',
  trade_type: 'buy_in',
  unit: 'jin',
  qty: 0,
  unit_price: 0,
  amount: 0,
  remark: '',
  images: [],
})
const rules = {
  venue_id: [{ required: true, message: '请输入 venue_id', trigger: 'blur' }],
}

function formatStorageUrl(u) {
  if (!u) return ''
  if (u.startsWith('http')) return u
  const base = import.meta.env.VITE_STORAGE_URL || ''
  return base + u
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getFishTradeList({
      page: page.value,
      limit: limit.value,
      session_id: filters.session_id || undefined,
      pond_id: filters.pond_id || undefined,
      trade_type: filters.trade_type || undefined,
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
  filters.trade_type = ''
  page.value = 1
  fetchList()
}

function openForm(row) {
  if (row) {
    editId.value = row.id
    form.venue_id = String(row.venue_id || '')
    form.pond_id = row.pond_id ? String(row.pond_id) : ''
    form.session_id = row.session_id ? String(row.session_id) : ''
    form.trade_type = row.trade_type || 'buy_in'
    form.unit = row.unit || 'jin'
    form.qty = Number(row.qty || 0)
    form.unit_price = Number(row.unit_price || 0)
    form.amount = Number(row.amount || 0)
    form.remark = row.remark || ''
    form.images = Array.isArray(row.images)
      ? row.images.map((u) => ({ url: u, file: null, preview: formatStorageUrl(u), isNew: false }))
      : []
  } else {
    editId.value = null
    form.venue_id = ''
    form.pond_id = filters.pond_id || ''
    form.session_id = filters.session_id || ''
    form.trade_type = 'buy_in'
    form.unit = 'jin'
    form.qty = 0
    form.unit_price = 0
    form.amount = 0
    form.remark = ''
    form.images = []
  }
  dialogVisible.value = true
}

function resetForm() {
  dialogVisible.value = false
  editId.value = null
  if (formRef.value) formRef.value.clearValidate()
}

function handleFileChange(uploadFile) {
  const file = uploadFile.raw
  if (!file) return
  const preview = URL.createObjectURL(file)
  form.images.push({ url: '', file, preview, isNew: true })
}

function removeImage(idx) {
  const img = form.images[idx]
  if (img && img.preview && img.preview.startsWith('blob:')) URL.revokeObjectURL(img.preview)
  form.images.splice(idx, 1)
}

async function submit() {
  if (!formRef.value) return
  await formRef.value.validate()
  try {
    submitLoading.value = true

    // 保存时统一上传新增图片
    for (const img of form.images) {
      if (img.isNew && img.file) {
        const res = await uploadImage(img.file)
        const url = res?.data?.url ?? res?.url ?? ''
        if (!url) {
          ElMessage.error('有图片上传失败，请重试')
          submitLoading.value = false
          return
        }
        img.url = url
        img.preview = formatStorageUrl(url)
        img.isNew = false
      }
    }

    const payload = {
      venue_id: Number(form.venue_id),
      pond_id: form.pond_id ? Number(form.pond_id) : undefined,
      session_id: form.session_id ? Number(form.session_id) : undefined,
      trade_type: form.trade_type,
      unit: form.unit,
      qty: form.qty,
      unit_price: form.unit_price,
      amount: form.amount,
      remark: form.remark,
      images: form.images.map((i) => i.url).filter((u) => u),
    }

    if (editId.value) {
      await updateFishTrade(editId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createFishTrade(payload)
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
    await ElMessageBox.confirm('确定删除该交易流水？', '提示', { type: 'warning' })
  } catch {
    return
  }
  await deleteFishTrade(row.id)
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
.thumb-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.thumb-item {
  position: relative;
  width: 86px;
  height: 86px;
}
.thumb-remove {
  position: absolute;
  right: -8px;
  top: -8px;
  width: 20px;
  height: 20px;
  line-height: 20px;
  border-radius: 10px;
  text-align: center;
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  cursor: pointer;
  user-select: none;
}
.upload-add-card :deep(.el-upload) {
  width: 86px;
  height: 86px;
  display: inline-flex;
}
.upload-add-inner {
  width: 86px;
  height: 86px;
  border: 1px dashed #cdd0d6;
  border-radius: 4px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #909399;
}
.upload-add-icon {
  font-size: 22px;
  line-height: 22px;
}
.upload-add-text {
  margin-top: 4px;
  font-size: 12px;
}
</style>

