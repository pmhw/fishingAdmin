<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>公共商品库</span>
          <el-button v-if="canEdit" type="primary" @click="openProductDialog()">新增商品</el-button>
        </div>
      </template>
      <el-form :inline="true" class="filter-form" @submit.prevent>
        <el-form-item label="关键词">
          <el-input v-model="keyword" placeholder="名称" clearable style="width: 200px" @keyup.enter="onSearch" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterStatus" placeholder="全部" clearable style="width: 100px" @change="onSearch">
            <el-option label="启用" :value="1" />
            <el-option label="停用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="onSearch">查询</el-button>
        </el-form-item>
      </el-form>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="封面" width="90">
          <template #default="{ row }">
            <el-image
              v-if="row.cover_image"
              :src="imgUrl(row.cover_image)"
              :preview-src-list="[imgUrl(row.cover_image)]"
              preview-teleported
              fit="cover"
              style="width: 56px; height: 56px; border-radius: 4px; cursor: pointer"
            />
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="unit" label="单位" width="70" />
        <el-table-column prop="sku_count" label="规格数" width="80" />
        <el-table-column prop="sort_order" label="排序" width="70" />
        <el-table-column label="库内状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column v-if="canEdit" label="操作" fixed="right" width="200">
          <template #default="{ row }">
            <el-button link type="primary" @click="openProductDialog(row)">编辑</el-button>
            <el-button link type="primary" @click="openSkuDialog(row)">规格</el-button>
            <el-button link type="danger" @click="onDeleteProduct(row)">删除</el-button>
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

    <!-- SPU -->
    <el-dialog v-model="productDialogVisible" :title="editProductId ? '编辑商品' : '新增商品'" width="560px" destroy-on-close @closed="resetProductForm">
      <el-form ref="productFormRef" :model="productForm" :rules="productRules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="productForm.name" placeholder="必填" />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="productForm.category_id" placeholder="未分类" style="width: 100%">
            <el-option label="未分类" :value="0" />
            <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="单位">
          <el-input v-model="productForm.unit" placeholder="如 件、包、瓶" />
        </el-form-item>
        <el-form-item label="简介">
          <el-input v-model="productForm.intro" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
        <el-form-item label="封面">
          <div class="upload-wrap">
            <el-image
              v-if="productForm.cover_image"
              :src="imgUrl(productForm.cover_image)"
              :preview-src-list="[imgUrl(productForm.cover_image)]"
              preview-teleported
              fit="cover"
              class="upload-preview-sm"
            />
            <el-upload :show-file-list="false" accept="image/*" :http-request="handleCoverUpload">
              <el-button type="primary" :loading="uploading">{{ productForm.cover_image ? '更换' : '上传' }}</el-button>
            </el-upload>
          </div>
        </el-form-item>
        <el-form-item label="多图URL">
          <el-input v-model="productForm.imagesText" type="textarea" :rows="3" placeholder="每行一个图片路径或完整 URL，选填" />
        </el-form-item>
        <el-form-item label="详情">
          <el-input v-model="productForm.detail" type="textarea" :rows="4" placeholder="选填，可填 HTML 或纯文本" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="productForm.sort_order" :min="0" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="productForm.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="productDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="productSubmitLoading" @click="submitProduct">保存</el-button>
      </template>
    </el-dialog>

    <!-- SKU -->
    <el-dialog v-model="skuDialogVisible" :title="`规格管理 — ${skuProductName}`" width="720px" destroy-on-close @closed="skuList = []">
      <el-table v-loading="skuLoading" :data="skuList" border size="small" max-height="360">
        <el-table-column prop="id" label="SKU ID" width="80" />
        <el-table-column prop="spec_label" label="规格名称" min-width="140" />
        <el-table-column label="建议价(元)" width="110">
          <template #default="{ row }">{{ row.default_price ?? '-' }}</template>
        </el-table-column>
        <el-table-column prop="sort_order" label="排序" width="70" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag size="small" :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column v-if="canEdit" label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openSkuEdit(row)">改</el-button>
            <el-button link type="danger" size="small" @click="onDeleteSku(row)">删</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-divider v-if="canEdit" content-position="left">新增规格</el-divider>
      <el-form v-if="canEdit" :inline="true" class="sku-add-form">
        <el-form-item label="规格名称">
          <el-input v-model="newSku.spec_label" placeholder="如 500ml×1袋" style="width: 200px" />
        </el-form-item>
        <el-form-item label="建议价">
          <el-input-number v-model="newSku.default_price" :min="0" :precision="2" controls-position="right" style="width: 120px" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="newSku.sort_order" :min="0" controls-position="right" style="width: 100px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="skuAddLoading" @click="submitNewSku">添加</el-button>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button type="primary" @click="skuDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="skuEditVisible" title="编辑规格" width="440px" destroy-on-close>
      <el-form label-width="100px">
        <el-form-item label="规格名称">
          <el-input v-model="editSkuForm.spec_label" />
        </el-form-item>
        <el-form-item label="建议价">
          <el-input-number v-model="editSkuForm.default_price" :min="0" :precision="2" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editSkuForm.sort_order" :min="0" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editSkuForm.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="skuEditVisible = false">取消</el-button>
        <el-button type="primary" :loading="skuEditLoading" @click="submitSkuEdit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useUserStore } from '@/stores/user'
import {
  getShopProductCategories,
  getShopProductList,
  getShopProductDetail,
  createShopProduct,
  updateShopProduct,
  deleteShopProduct,
  addShopProductSku,
  updateShopProductSku,
  deleteShopProductSku,
  uploadShopImage,
} from '@/api/shop'

const userStore = useUserStore()
const canEdit = computed(() => {
  const p = userStore.user?.permissions
  if (!Array.isArray(p)) return false
  return p.includes('*') || p.includes('admin.shop.product.manage')
})

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)
const keyword = ref('')
const filterStatus = ref('')

const categories = ref([])

function imgUrl(u) {
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u
  return import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + u : u
}

function imagesTextToArray(text) {
  if (!text || !String(text).trim()) return []
  return String(text)
    .split(/\r?\n/)
    .map((s) => s.trim())
    .filter(Boolean)
}

function onSearch() {
  page.value = 1
  fetchList()
}

async function fetchCategories() {
  try {
    const res = await getShopProductCategories()
    categories.value = res.data?.list ?? []
  } catch (_) {
    categories.value = []
  }
}

async function fetchList() {
  loading.value = true
  try {
    const params = { page: page.value, limit: limit.value }
    if (keyword.value.trim()) params.keyword = keyword.value.trim()
    if (filterStatus.value !== '' && filterStatus.value !== null) params.status = filterStatus.value
    const res = await getShopProductList(params)
    list.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

const productDialogVisible = ref(false)
const editProductId = ref(null)
const productFormRef = ref(null)
const productSubmitLoading = ref(false)
const uploading = ref(false)
const productForm = reactive({
  name: '',
  category_id: 0,
  unit: '件',
  intro: '',
  cover_image: '',
  imagesText: '',
  detail: '',
  sort_order: 0,
  status: 1,
})
const productRules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
}

function resetProductForm() {
  editProductId.value = null
  productForm.name = ''
  productForm.category_id = 0
  productForm.unit = '件'
  productForm.intro = ''
  productForm.cover_image = ''
  productForm.imagesText = ''
  productForm.detail = ''
  productForm.sort_order = 0
  productForm.status = 1
}

async function openProductDialog(row) {
  await fetchCategories()
  resetProductForm()
  if (row?.id) {
    editProductId.value = row.id
    try {
      const res = await getShopProductDetail(row.id)
      const d = res.data
      if (d) {
        productForm.name = d.name ?? ''
        productForm.category_id = d.category_id ?? 0
        productForm.unit = d.unit ?? '件'
        productForm.intro = d.intro ?? ''
        productForm.cover_image = d.cover_image ?? ''
        const imgs = Array.isArray(d.images) ? d.images : []
        productForm.imagesText = imgs.join('\n')
        productForm.detail = d.detail ?? ''
        productForm.sort_order = d.sort_order ?? 0
        productForm.status = d.status ?? 1
      }
    } catch (_) {}
  }
  productDialogVisible.value = true
}

async function handleCoverUpload({ file }) {
  if (!file) return
  uploading.value = true
  try {
    const res = await uploadShopImage(file)
    productForm.cover_image = res.data?.url ?? ''
    ElMessage.success('上传成功')
  } catch (_) {
    ElMessage.error('上传失败')
  } finally {
    uploading.value = false
  }
}

async function submitProduct() {
  await productFormRef.value?.validate().catch(() => Promise.reject())
  productSubmitLoading.value = true
  try {
    const payload = {
      name: productForm.name.trim(),
      category_id: productForm.category_id || 0,
      unit: productForm.unit || '件',
      intro: productForm.intro || null,
      cover_image: productForm.cover_image || null,
      images: imagesTextToArray(productForm.imagesText),
      detail: productForm.detail || null,
      sort_order: productForm.sort_order,
      status: productForm.status,
    }
    if (editProductId.value) {
      await updateShopProduct(editProductId.value, payload)
      ElMessage.success('已保存')
    } else {
      await createShopProduct(payload)
      ElMessage.success('已创建')
    }
    productDialogVisible.value = false
    fetchList()
  } finally {
    productSubmitLoading.value = false
  }
}

function onDeleteProduct(row) {
  ElMessageBox.confirm(`确定删除商品「${row.name}」？若已被店铺引用可能删除失败。`, '提示', {
    type: 'warning',
  })
    .then(async () => {
      await deleteShopProduct(row.id)
      ElMessage.success('已删除')
      fetchList()
    })
    .catch(() => {})
}

/* ---- SKU ---- */
const skuDialogVisible = ref(false)
const skuLoading = ref(false)
const skuProductId = ref(0)
const skuProductName = ref('')
const skuList = ref([])
const skuAddLoading = ref(false)
const newSku = reactive({ spec_label: '', default_price: undefined, sort_order: 0 })

async function openSkuDialog(row) {
  skuProductId.value = row.id
  skuProductName.value = row.name ?? ''
  skuDialogVisible.value = true
  skuLoading.value = true
  try {
    const res = await getShopProductDetail(row.id)
    skuList.value = res.data?.skus ?? []
  } finally {
    skuLoading.value = false
  }
}

async function submitNewSku() {
  if (!newSku.spec_label?.trim()) {
    ElMessage.warning('请填写规格名称')
    return
  }
  skuAddLoading.value = true
  try {
    await addShopProductSku(skuProductId.value, {
      spec_label: newSku.spec_label.trim(),
      default_price: newSku.default_price,
      sort_order: newSku.sort_order,
      status: 1,
    })
    ElMessage.success('已添加')
    newSku.spec_label = ''
    newSku.default_price = undefined
    newSku.sort_order = 0
    const res = await getShopProductDetail(skuProductId.value)
    skuList.value = res.data?.skus ?? []
    fetchList()
  } finally {
    skuAddLoading.value = false
  }
}

const skuEditVisible = ref(false)
const skuEditLoading = ref(false)
const editSkuForm = reactive({ id: 0, spec_label: '', default_price: undefined, sort_order: 0, status: 1 })

function openSkuEdit(row) {
  editSkuForm.id = row.id
  editSkuForm.spec_label = row.spec_label ?? ''
  editSkuForm.default_price = row.default_price != null ? Number(row.default_price) : undefined
  editSkuForm.sort_order = row.sort_order ?? 0
  editSkuForm.status = row.status ?? 1
  skuEditVisible.value = true
}

async function submitSkuEdit() {
  skuEditLoading.value = true
  try {
    await updateShopProductSku(editSkuForm.id, {
      spec_label: editSkuForm.spec_label,
      default_price: editSkuForm.default_price,
      sort_order: editSkuForm.sort_order,
      status: editSkuForm.status,
    })
    ElMessage.success('已保存')
    skuEditVisible.value = false
    const res = await getShopProductDetail(skuProductId.value)
    skuList.value = res.data?.skus ?? []
    fetchList()
  } finally {
    skuEditLoading.value = false
  }
}

function onDeleteSku(row) {
  ElMessageBox.confirm('确定删除该规格？已被店铺使用的可能失败。', '提示', { type: 'warning' })
    .then(async () => {
      await deleteShopProductSku(row.id)
      ElMessage.success('已删除')
      const res = await getShopProductDetail(skuProductId.value)
      skuList.value = res.data?.skus ?? []
      fetchList()
    })
    .catch(() => {})
}

onMounted(() => {
  fetchList()
})
</script>

<style scoped>
.page {
  max-width: 1400px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.filter-form {
  margin-bottom: 12px;
}
.text-muted {
  color: var(--el-text-color-secondary);
}
.upload-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}
.upload-preview-sm {
  width: 72px;
  height: 72px;
  border-radius: 4px;
}
.sku-add-form {
  margin-top: 8px;
}
</style>
