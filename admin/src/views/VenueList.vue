<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>钓场列表</span>
          <el-button type="primary" @click="openEdit()">新增钓场</el-button>
        </div>
      </template>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="封面" width="100">
          <template #default="{ row }">
            <el-image
              v-if="row.cover_image"
              :src="getImageDisplayUrl(row.cover_image)"
              :preview-src-list="[getImageDisplayUrl(row.cover_image)]"
              preview-teleported
              fit="cover"
              style="width: 72px; height: 48px; border-radius: 4px; cursor: pointer"
            />
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" min-width="140" show-overflow-tooltip />
        <el-table-column label="地区" width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ [row.province, row.city, row.district].filter(Boolean).join(' ') || '-' }}</template>
        </el-table-column>
        <el-table-column prop="price_info" label="价格" width="100" show-overflow-tooltip />
        <el-table-column prop="sort_order" label="排序" width="70" />
        <el-table-column label="显示" width="80">
          <template #default="{ row }">
            <el-switch
              :model-value="row.status === 1"
              @update:model-value="(v) => onStatusChange(row, v)"
            />
          </template>
        </el-table-column>
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

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑钓场' : '新增钓场'" width="680px" @close="resetForm">
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="100px" class="venue-form">
        <el-divider content-position="left">基础信息</el-divider>
        <el-form-item label="钓场名称" prop="name">
          <el-input v-model="editForm.name" placeholder="必填" />
        </el-form-item>
        <el-form-item label="简短简介">
          <el-input v-model="editForm.intro" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="封面图">
          <div class="upload-wrap">
            <el-image
              v-if="editForm.cover_image"
              :src="coverDisplayUrl"
              :preview-src-list="[coverDisplayUrl]"
              preview-teleported
              fit="cover"
              class="upload-preview"
            />
            <el-upload :show-file-list="false" accept="image/*" :http-request="handleCoverUpload">
              <el-button type="primary" :loading="uploading">{{ editForm.cover_image ? '更换' : '上传' }}</el-button>
            </el-upload>
          </div>
        </el-form-item>
        <el-form-item label="详细描述">
          <div class="rich-editor-wrap" v-if="dialogVisible">
            <Toolbar class="rich-toolbar" :editor="editorRef" :defaultConfig="toolbarConfig" />
            <Editor class="rich-editor" v-model="descriptionHtml" :defaultConfig="editorConfig" @onCreated="onEditorCreated" />
          </div>
        </el-form-item>
        <el-divider content-position="left">位置与联系</el-divider>
        <el-form-item label="地图选点">
          <el-button type="primary" plain @click="openMapPicker">打开地图选点</el-button>
          <span class="form-tip">在地图上点击选点或搜索地点后，点击弹窗内「确定」即可回填到下方表单</span>
          <div v-if="editForm.address || editForm.longitude" class="address-preview">
            已选：{{ editForm.address || `经度 ${editForm.longitude} 纬度 ${editForm.latitude}` }}
          </div>
        </el-form-item>
        <AmapPointPicker
          v-model="mapPickerVisible"
          :default-center="mapPickerCenter"
          @confirm="onMapPickerConfirm"
        />
        <el-row :gutter="12">
          <el-col :span="8"><el-form-item label="省"><el-input v-model="editForm.province" placeholder="选填或地图选点回填" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="市"><el-input v-model="editForm.city" placeholder="选填或地图选点回填" /></el-form-item></el-col>
          <el-col :span="8"><el-form-item label="区/县"><el-input v-model="editForm.district" placeholder="选填或地图选点回填" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="详细地址">
          <el-input v-model="editForm.address" placeholder="选填或地图选点回填" />
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="经度"><el-input v-model="editForm.longitude" placeholder="选填或地图选点回填" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="纬度"><el-input v-model="editForm.latitude" placeholder="选填或地图选点回填" /></el-form-item></el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="联系电话"><el-input v-model="editForm.contact_phone" placeholder="选填" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="微信/客服"><el-input v-model="editForm.contact_wechat" placeholder="选填" /></el-form-item></el-col>
        </el-row>
        <el-divider content-position="left">营业与收费</el-divider>
        <el-form-item label="营业时间">
          <el-time-picker
            v-model="openingHoursRange"
            is-range
            value-format="HH:mm"
            format="HH:mm"
            start-placeholder="开始时间"
            end-placeholder="结束时间"
            placeholder="选择营业时间段"
            style="width: 100%"
          />
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="计费方式">
              <el-select v-model="editForm.price_type" placeholder="选填" clearable style="width:100%">
                <el-option label="按天" value="day" />
                <el-option label="按斤" value="jin" />
                <el-option label="混合" value="mix" />
                <el-option label="免费" value="free" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="价格说明">
              <el-input v-model="editForm.price_info" placeholder="如 80元/天" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="12">
          <el-col :span="12"><el-form-item label="最低价(元)"><el-input-number v-model="editForm.price_min" :min="0" :precision="2" controls-position="right" style="width:100%" /></el-form-item></el-col>
          <el-col :span="12"><el-form-item label="最高价(元)"><el-input-number v-model="editForm.price_max" :min="0" :precision="2" controls-position="right" style="width:100%" /></el-form-item></el-col>
        </el-row>
        <el-form-item label="设施">
          <div class="tag-field">
            <div class="tag-hint">如：停车场、餐饮、厕所 — 输入后按 <kbd>回车</kbd> 或点击框外自动添加，点击标签 × 删除</div>
            <div class="tag-input-wrap">
              <el-tag
                v-for="(item, idx) in facilitiesTags"
                :key="'f-' + idx"
                closable
                size="small"
                class="tag-item"
                @close="removeFacilityTag(idx)"
              >
                {{ item }}
              </el-tag>
              <el-input
                v-model="facilityInput"
                placeholder="输入设施名，按回车或点击外部添加"
                size="small"
                class="tag-input"
                @keyup.enter="addFacilityTag"
                @blur="addFacilityTag"
              />
            </div>
          </div>
        </el-form-item>
        <el-form-item label="鱼种">
          <div class="tag-field">
            <div class="tag-hint">如：鲫鱼、鲤鱼、草鱼 — 输入后按 <kbd>回车</kbd> 或点击框外自动添加，点击标签 × 删除</div>
            <div class="tag-input-wrap">
              <el-tag
                v-for="(item, idx) in fishSpeciesTags"
                :key="'s-' + idx"
                closable
                size="small"
                class="tag-item"
                @close="removeFishSpeciesTag(idx)"
              >
                {{ item }}
              </el-tag>
              <el-input
                v-model="fishSpeciesInput"
                placeholder="输入鱼种名，按回车或点击外部添加"
                size="small"
                class="tag-input"
                @keyup.enter="addFishSpeciesTag"
                @blur="addFishSpeciesTag"
              />
            </div>
          </div>
        </el-form-item>
        <el-divider content-position="left">状态与排序</el-divider>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="状态">
              <el-radio-group v-model="editForm.status">
                <el-radio :value="1">上架</el-radio>
                <el-radio :value="0">下架</el-radio>
                <el-radio :value="2">待审核</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序">
              <el-input-number v-model="editForm.sort_order" :min="0" controls-position="right" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="submitEdit">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted, shallowRef, onBeforeUnmount } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getVenueList, getVenueDetail, createVenue, updateVenue, updateVenueStatus, deleteVenue, uploadImage } from '@/api/venue'
import AmapPointPicker from '@/components/AmapPointPicker.vue'
import '@wangeditor/editor/dist/css/style.css'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const dialogVisible = ref(false)
const editId = ref(null)
const editFormRef = ref(null)
const submitLoading = ref(false)
const uploading = ref(false)

// 富文本
const editorRef = shallowRef(null)
const descriptionHtml = ref('')
const toolbarConfig = {}
const editorConfig = { placeholder: '请输入详细描述，支持富文本', MENU_CONF: {} }
function onEditorCreated(editor) {
  editorRef.value = editor
}
onBeforeUnmount(() => {
  const editor = editorRef.value
  if (editor) editor.destroy()
})

// 营业时间范围 ["08:00", "18:00"]
const openingHoursRange = ref([])
// 设施、鱼种标签（回车添加，提交时逗号拼接入库）
const facilitiesTags = ref([])
const fishSpeciesTags = ref([])
const facilityInput = ref('')
const fishSpeciesInput = ref('')

function addFacilityTag() {
  const v = (facilityInput.value || '').trim()
  if (!v) return
  if (facilitiesTags.value.includes(v)) {
    facilityInput.value = ''
    return
  }
  facilitiesTags.value = [...facilitiesTags.value, v]
  facilityInput.value = ''
}
function removeFacilityTag(index) {
  facilitiesTags.value = facilitiesTags.value.filter((_, i) => i !== index)
}
function addFishSpeciesTag() {
  const v = (fishSpeciesInput.value || '').trim()
  if (!v) return
  if (fishSpeciesTags.value.includes(v)) {
    fishSpeciesInput.value = ''
    return
  }
  fishSpeciesTags.value = [...fishSpeciesTags.value, v]
  fishSpeciesInput.value = ''
}
function removeFishSpeciesTag(index) {
  fishSpeciesTags.value = fishSpeciesTags.value.filter((_, i) => i !== index)
}

// 地图选点
const mapPickerVisible = ref(false)
const mapPickerCenter = computed(() => {
  const lng = parseFloat(editForm.longitude)
  const lat = parseFloat(editForm.latitude)
  if (!Number.isFinite(lng) || !Number.isFinite(lat)) return null
  return [lng, lat]
})

function openMapPicker() {
  mapPickerVisible.value = true
}
function onMapPickerConfirm(res) {
  if (res == null) return
  editForm.longitude = res.longitude ?? ''
  editForm.latitude = res.latitude ?? ''
  editForm.address = res.address ?? ''
  editForm.province = res.province ?? ''
  editForm.city = res.city ?? ''
  editForm.district = res.district ?? ''
}

const editForm = reactive({
  name: '',
  intro: '',
  description: '',
  cover_image: '',
  province: '',
  city: '',
  district: '',
  address: '',
  longitude: '',
  latitude: '',
  contact_phone: '',
  contact_wechat: '',
  opening_hours: '',
  price_type: '',
  price_info: '',
  price_min: null,
  price_max: null,
  facilities: '',
  fish_species: '',
  status: 1,
  sort_order: 0,
})
const editRules = {
  name: [{ required: true, message: '请输入钓场名称', trigger: 'blur' }],
}

const coverDisplayUrl = computed(() => {
  const u = editForm.cover_image
  if (!u) return ''
  return /^https?:\/\//i.test(u) ? u : (import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + u : u)
})

function getImageDisplayUrl(url) {
  if (!url) return ''
  return /^https?:\/\//i.test(url) ? url : (import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + url : url)
}

async function handleCoverUpload({ file }) {
  if (!file) return
  uploading.value = true
  try {
    const res = await uploadImage(file)
    editForm.cover_image = res.data?.url ?? ''
    ElMessage.success('上传成功')
  } catch (_) {
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getVenueList({ page: page.value, limit: limit.value })
    list.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editId.value = row?.id ?? null
  Object.assign(editForm, {
    name: row?.name ?? '',
    intro: row?.intro ?? '',
    description: row?.description ?? '',
    cover_image: row?.cover_image ?? '',
    province: row?.province ?? '',
    city: row?.city ?? '',
    district: row?.district ?? '',
    address: row?.address ?? '',
    longitude: row?.longitude ?? '',
    latitude: row?.latitude ?? '',
    contact_phone: row?.contact_phone ?? '',
    contact_wechat: row?.contact_wechat ?? '',
    opening_hours: row?.opening_hours ?? '',
    price_type: row?.price_type ?? '',
    price_info: row?.price_info ?? '',
    price_min: row?.price_min ?? null,
    price_max: row?.price_max ?? null,
    facilities: row?.facilities ?? '',
    fish_species: row?.fish_species ?? '',
    status: row?.status ?? 1,
    sort_order: row?.sort_order ?? 0,
  })
  descriptionHtml.value = editForm.description || ''
  const str = (editForm.opening_hours || '').trim()
  const parts = str ? str.split('-').map(s => s.trim()) : []
  openingHoursRange.value = parts.length === 2 ? parts : []
  facilitiesTags.value = (editForm.facilities || '').split(',').map((s) => s.trim()).filter(Boolean)
  fishSpeciesTags.value = (editForm.fish_species || '').split(',').map((s) => s.trim()).filter(Boolean)
  facilityInput.value = ''
  fishSpeciesInput.value = ''
  dialogVisible.value = true
  if (editId.value) {
    getVenueDetail(editId.value).then((res) => {
      const d = res.data
      if (d) {
        Object.assign(editForm, d)
        descriptionHtml.value = d.description || ''
        const oh = (d.opening_hours || '').trim()
        const p = oh ? oh.split('-').map(s => s.trim()) : []
        openingHoursRange.value = p.length === 2 ? p : []
        facilitiesTags.value = (d.facilities || '').split(',').map((s) => s.trim()).filter(Boolean)
        fishSpeciesTags.value = (d.fish_species || '').split(',').map((s) => s.trim()).filter(Boolean)
      }
    })
  }
}

function resetForm() {
  descriptionHtml.value = ''
  openingHoursRange.value = []
  facilitiesTags.value = []
  fishSpeciesTags.value = []
  facilityInput.value = ''
  fishSpeciesInput.value = ''
  editFormRef.value?.resetFields?.()
}

async function submitEdit() {
  await editFormRef.value?.validate().catch(() => {})
  submitLoading.value = true
  try {
    const payload = { ...editForm }
    payload.description = descriptionHtml.value || ''
    payload.opening_hours = Array.isArray(openingHoursRange.value) && openingHoursRange.value.length === 2
      ? openingHoursRange.value.join('-')
      : (editForm.opening_hours || '')
    payload.facilities = Array.isArray(facilitiesTags.value) ? facilitiesTags.value.join(',') : (editForm.facilities || '')
    payload.fish_species = Array.isArray(fishSpeciesTags.value) ? fishSpeciesTags.value.join(',') : (editForm.fish_species || '')
    if (payload.price_min === null || payload.price_min === '') payload.price_min = null
    if (payload.price_max === null || payload.price_max === '') payload.price_max = null
    if (editId.value) {
      await updateVenue(editId.value, payload)
      ElMessage.success('更新成功')
    } else {
      await createVenue(payload)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    fetchList()
  } finally {
    submitLoading.value = false
  }
}

async function onStatusChange(row, show) {
  const status = show ? 1 : 0
  try {
    await updateVenueStatus(row.id, status)
    row.status = status
    ElMessage.success(show ? '已显示' : '已隐藏')
  } catch (_) {
    fetchList()
  }
}

function onDelete(row) {
  ElMessageBox.confirm('确定删除该钓场？', '提示', { type: 'warning' })
    .then(async () => {
      await deleteVenue(row.id)
      ElMessage.success('已删除')
      fetchList()
    })
    .catch(() => {})
}

onMounted(() => fetchList())
</script>
<style scoped>
.card-header { display: flex; justify-content: space-between; align-items: center; }
.text-muted { color: var(--el-text-color-secondary); font-size: 12px; }
.venue-form { max-height: 70vh; overflow-y: auto; }
.upload-wrap { display: flex; flex-direction: column; gap: 8px; }
.upload-preview { width: 160px; height: 90px; border-radius: 6px; border: 1px solid var(--el-border-color); cursor: pointer; }
.rich-editor-wrap { border: 1px solid var(--el-border-color); border-radius: 4px; overflow: hidden; }
.rich-toolbar { border-bottom: 1px solid var(--el-border-color); }
.rich-editor { min-height: 280px; }
.form-tip { display: block; margin-top: 6px; color: var(--el-text-color-secondary); font-size: 12px; }
.address-preview { display: block; margin-top: 6px; color: var(--el-text-color-primary); font-size: 13px; max-width: 100%; }
.tag-field { display: flex; flex-direction: column; gap: 6px; }
.tag-hint { font-size: 12px; color: var(--el-text-color-secondary); line-height: 1.3; }
.tag-hint kbd { padding: 1px 4px; font-size: 11px; border-radius: 3px; background: var(--el-fill-color); border: 1px solid var(--el-border-color-lighter); }
.tag-input-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 4px 8px; min-height: 32px; border: 1px solid var(--el-border-color); border-radius: 4px; background: var(--el-fill-color-blank); }
.tag-input-wrap .tag-item { margin: 0; flex-shrink: 0; }
.tag-input-wrap .tag-input { min-width: 280px; flex: 1; }
.tag-input-wrap .tag-input :deep(.el-input__wrapper) { box-shadow: none; padding-left: 6px; }
</style>
