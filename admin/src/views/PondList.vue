<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>池塘列表</span>
          <el-button type="primary" @click="openEdit()">添加池塘</el-button>
        </div>
      </template>
      <el-form inline class="filter-form">
        <el-form-item label="钓场">
          <el-select v-model="filterVenueId" placeholder="全部" clearable style="width: 180px" @change="fetchList">
            <el-option v-for="v in venueOptions" :key="v.id" :label="v.name" :value="v.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="venue_name" label="所属钓场" min-width="120" show-overflow-tooltip />
        <el-table-column prop="name" label="池塘名称" min-width="120" show-overflow-tooltip />
        <el-table-column label="类型" width="90">
          <template #default="{ row }">{{ pondTypeLabel(row.pond_type) }}</template>
        </el-table-column>
        <el-table-column prop="seat_count" label="钓位数" width="90" />
        <el-table-column prop="area_mu" label="面积(亩)" width="90" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'open' ? 'success' : 'info'" size="small">
              {{ row.status === 'open' ? '开放' : '关闭' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="70" />
        <el-table-column prop="created_at" label="创建时间" width="170" />
        <el-table-column label="操作" fixed="right" width="140">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑池塘' : '添加池塘'" width="560px" @close="resetForm">
      <el-form ref="formRef" :model="editForm" :rules="editRules" label-width="100px">
        <el-form-item label="所属钓场" prop="venue_id">
          <el-select v-model="editForm.venue_id" placeholder="请选择钓场" style="width:100%" :disabled="!!editId">
            <el-option v-for="v in venueOptions" :key="v.id" :label="v.name" :value="v.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="池塘名称" prop="name">
          <el-input v-model="editForm.name" placeholder="必填" />
        </el-form-item>
        <el-form-item label="池塘类型">
          <el-select v-model="editForm.pond_type" placeholder="请选择" style="width:100%">
            <el-option label="黑坑" value="black_pit" />
            <el-option label="斤塘" value="jin_tang" />
            <el-option label="练杆塘" value="practice" />
          </el-select>
        </el-form-item>
        <el-form-item label="池塘图片">
          <div class="upload-wrap">
            <el-image
              v-if="coverImageUrl"
              :src="coverImageUrl"
              :preview-src-list="[coverImageUrl]"
              preview-teleported
              fit="cover"
              class="upload-preview"
            />
            <el-upload :show-file-list="false" accept="image/*" :http-request="handleImageUpload">
              <el-button type="primary" :loading="uploading">{{ coverImageUrl ? '更换' : '上传' }}</el-button>
            </el-upload>
          </div>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="钓位数">
              <el-input-number v-model="editForm.seat_count" :min="0" controls-position="right" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="面积(亩)">
              <el-input-number v-model="editForm.area_mu" :min="0" :precision="2" controls-position="right" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="水深">
          <el-input v-model="editForm.water_depth" placeholder="如 1.5-2米" />
        </el-form-item>
        <el-form-item label="鱼种">
          <el-input v-model="editForm.fish_species" placeholder="逗号分隔，如 鲫鱼,鲤鱼" />
        </el-form-item>
        <el-form-item label="限杆规则">
          <el-input v-model="editForm.rod_rule" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="限饵规则">
          <el-input v-model="editForm.bait_rule" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="补充规则">
          <el-input v-model="editForm.extra_rule" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="开塘时间">
          <el-date-picker v-model="editForm.open_time" type="date" value-format="YYYY-MM-DD" placeholder="选填" style="width:100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio value="open">开放</el-radio>
            <el-radio value="closed">关闭</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort_order" :min="0" controls-position="right" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitEdit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPondList, getPondDetail, createPond, updatePond, deletePond, getVenueOptions, uploadImage } from '@/api/pond'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const filterVenueId = ref('')
const venueOptions = ref([])

const dialogVisible = ref(false)
const editId = ref(null)
const formRef = ref(null)
const submitLoading = ref(false)
const uploading = ref(false)

const editForm = reactive({
  venue_id: null,
  name: '',
  images: '',
  pond_type: 'black_pit',
  seat_count: 0,
  area_mu: null,
  water_depth: '',
  fish_species: '',
  rod_rule: '',
  bait_rule: '',
  extra_rule: '',
  open_time: null,
  status: 'open',
  sort_order: 0,
})

const editRules = {
  venue_id: [{ required: true, message: '请选择所属钓场', trigger: 'change' }],
  name: [{ required: true, message: '请输入池塘名称', trigger: 'blur' }],
}

function pondTypeLabel(type) {
  const map = { black_pit: '黑坑', jin_tang: '斤塘', practice: '练杆塘' }
  return map[type] || type
}

const coverImageUrl = computed(() => {
  let urls = []
  try {
    urls = typeof editForm.images === 'string' ? (JSON.parse(editForm.images || '[]') || []) : (editForm.images || [])
  } catch (_) {
    urls = []
  }
  const u = Array.isArray(urls) && urls.length ? urls[0] : ''
  return u ? (u.startsWith('http') ? u : (import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + u : u)) : ''
})

async function handleImageUpload({ file }) {
  if (!file) return
  uploading.value = true
  try {
    const res = await uploadImage(file)
    const url = res.data?.url ?? res?.url ?? ''
    if (url) editForm.images = JSON.stringify([url])
    ElMessage.success('上传成功')
  } catch (_) {
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}

async function loadVenueOptions() {
  try {
    const res = await getVenueOptions()
    const data = res?.data ?? res
    venueOptions.value = data?.list ?? []
  } catch (_) {
    venueOptions.value = []
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getPondList({
      page: page.value,
      limit: limit.value,
      venue_id: filterVenueId.value || undefined,
    })
    const data = res?.data ?? res
    list.value = data?.list ?? []
    total.value = data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editId.value = row?.id ?? null
  editForm.venue_id = row?.venue_id ?? null
  editForm.name = row?.name ?? ''
  editForm.images = row?.images ?? ''
  editForm.pond_type = row?.pond_type ?? 'black_pit'
  editForm.seat_count = row?.seat_count ?? 0
  editForm.area_mu = row?.area_mu ?? null
  editForm.water_depth = row?.water_depth ?? ''
  editForm.fish_species = row?.fish_species ?? ''
  editForm.rod_rule = row?.rod_rule ?? ''
  editForm.bait_rule = row?.bait_rule ?? ''
  editForm.extra_rule = row?.extra_rule ?? ''
  editForm.open_time = row?.open_time ?? null
  editForm.status = row?.status ?? 'open'
  editForm.sort_order = row?.sort_order ?? 0
  dialogVisible.value = true
  if (editId.value) {
    getPondDetail(editId.value).then((res) => {
      const d = res?.data ?? res
      if (d) {
        editForm.venue_id = d.venue_id ?? editForm.venue_id
        editForm.name = d.name ?? ''
        editForm.images = Array.isArray(d.images) ? JSON.stringify(d.images) : (d.images ?? '')
        editForm.pond_type = d.pond_type ?? 'black_pit'
        editForm.seat_count = d.seat_count ?? 0
        editForm.area_mu = d.area_mu ?? null
        editForm.water_depth = d.water_depth ?? ''
        editForm.fish_species = d.fish_species ?? ''
        editForm.rod_rule = d.rod_rule ?? ''
        editForm.bait_rule = d.bait_rule ?? ''
        editForm.extra_rule = d.extra_rule ?? ''
        editForm.open_time = d.open_time ?? null
        editForm.status = d.status ?? 'open'
        editForm.sort_order = d.sort_order ?? 0
      }
    })
  }
}

function resetForm() {
  formRef.value?.resetFields?.()
}

async function submitEdit() {
  await formRef.value?.validate().catch(() => {})
  submitLoading.value = true
  try {
    const payload = { ...editForm }
    if (payload.open_time === '') payload.open_time = null
    if (editId.value) {
      await updatePond(editId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createPond(payload)
      ElMessage.success('添加成功')
    }
    dialogVisible.value = false
    fetchList()
  } catch (_) {
    // error already shown by request
  } finally {
    submitLoading.value = false
  }
}

function onDelete(row) {
  ElMessageBox.confirm('确定删除该池塘？删除后其钓位区域、收费规则、回鱼规则也会一并删除。', '提示', { type: 'warning' })
    .then(async () => {
      await deletePond(row.id)
      ElMessage.success('已删除')
      fetchList()
    })
    .catch(() => {})
}

onMounted(() => {
  loadVenueOptions()
  fetchList()
})
</script>

<style scoped>
.card-header { display: flex; justify-content: space-between; align-items: center; }
.filter-form { margin-bottom: 12px; }
.upload-wrap { display: flex; flex-direction: column; gap: 8px; }
.upload-preview { width: 160px; height: 90px; border-radius: 6px; border: 1px solid var(--el-border-color); cursor: pointer; }
</style>
