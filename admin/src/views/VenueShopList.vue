<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>钓场店铺 — 选品与库存</span>
          <div class="header-actions">
            <el-select
              v-model="venueId"
              placeholder="请选择钓场"
              filterable
              clearable
              style="width: 220px"
              @change="onVenueChange"
            >
              <el-option v-for="v in venueOptions" :key="v.id" :label="v.name" :value="v.id" />
            </el-select>
            <el-button v-if="canVenue && venueId" @click="openCategoryDialog">本店分类</el-button>
            <el-button v-if="canVenue && venueId" type="primary" @click="openPickDialog">从商品库添加</el-button>
          </div>
        </div>
      </template>
      <el-alert v-if="!venueId" type="info" show-icon :closable="false" title="请先选择要管理的钓场；商品分类仅对本店有效，在「本店分类」中维护。" style="margin-bottom: 12px" />
      <template v-else>
        <el-form :inline="true" class="filter-form" @submit.prevent>
          <el-form-item label="本店分类">
            <el-select v-model="filterCategoryId" clearable placeholder="全部分组" style="width: 180px" @change="onFilterCategory">
              <el-option label="未分组" :value="0" />
              <el-option v-for="c in categoryOptions" :key="c.id" :label="c.name" :value="c.id" />
            </el-select>
          </el-form-item>
        </el-form>
        <el-table v-loading="loading" :data="list" stripe>
          <el-table-column prop="id" label="VP ID" width="80" />
          <el-table-column label="本店分类" width="120" show-overflow-tooltip>
            <template #default="{ row }">{{ row.shop_category?.name || '未分组' }}</template>
          </el-table-column>
          <el-table-column label="商品" min-width="200">
            <template #default="{ row }">
              <div class="prod-cell">
                <el-image
                  v-if="row.product?.cover_image"
                  :src="imgUrl(row.product.cover_image)"
                  fit="cover"
                  class="prod-thumb"
                />
                <span>{{ row.product?.name ?? '-' }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="sort_order" label="排序" width="80" />
          <el-table-column label="本店上架" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '在售' : '下架' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="规格数" width="80">
            <template #default="{ row }">{{ row.skus?.length ?? 0 }}</template>
          </el-table-column>
          <el-table-column v-if="canVenue" label="操作" fixed="right" width="280">
            <template #default="{ row }">
              <el-button link type="primary" @click="openStockDrawer(row)">售价/库存</el-button>
              <el-button link type="warning" @click="onSyncSkus(row)">同步新规格</el-button>
              <el-button link type="primary" @click="openVpEdit(row)">设置</el-button>
              <el-button link type="danger" @click="onRemove(row)">移除</el-button>
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
      </template>
    </el-card>

    <!-- 本店分类：回鱼规则式表内编辑 -->
    <el-dialog
      v-model="catVisible"
      :title="'本店商品分类 - ' + (currentVenueName || '')"
      width="640px"
      :close-on-click-modal="false"
      @open="onCategoryDialogOpen"
      @close="closeCategoryDialog"
    >
      <div v-loading="catListLoading">
        <div class="spec-toolbar">
          <span class="spec-tip">用于本店商品分组展示；删除分类后商品变为「未分组」</span>
          <el-button v-if="canVenue" type="primary" size="small" @click="addCatRow">添加</el-button>
        </div>
        <el-table :data="catDisplayList" size="small" max-height="360" stripe>
          <el-table-column label="分类名称" min-width="140">
            <template #default="{ row }">
              <el-input v-model="row.name" placeholder="如 饵料" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="排序" width="90">
            <template #default="{ row }">
              <el-input-number v-model="row.sort_order" :min="0" controls-position="right" size="small" style="width: 100%" />
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-select v-model="row.status" size="small" style="width: 100%">
                <el-option label="启用" :value="1" />
                <el-option label="停用" :value="0" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column v-if="canVenue" label="操作" width="140" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" link size="small" :loading="savingCatKey === getCatRowKey(row)" @click="saveCatRow(row)">保存</el-button>
              <el-button type="danger" link size="small" @click="deleteCatRow(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-dialog>

    <!-- 从库中添加 -->
    <el-dialog v-model="pickVisible" title="选择要加入店铺的商品" width="800px" destroy-on-close @open="onPickOpen">
      <el-form :inline="true" @submit.prevent>
        <el-form-item label="归入分类">
          <el-select v-model="pickShopCategoryId" clearable placeholder="未分组" style="width: 200px">
            <el-option v-for="c in categoryOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="pickKeyword" clearable placeholder="名称" style="width: 200px" @keyup.enter="pickPage = 1; loadAvailable()" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="pickPage = 1; loadAvailable()">查询</el-button>
        </el-form-item>
      </el-form>
      <el-table v-loading="pickLoading" :data="pickList" border max-height="400" size="small">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="name" label="名称" min-width="160" show-overflow-tooltip />
        <el-table-column prop="unit" label="单位" width="70" />
        <el-table-column prop="sku_count" label="规格数" width="80" />
        <el-table-column label="操作" width="100">
          <template #default="{ row }">
            <el-button type="primary" link @click="addOne(row)">加入店铺</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        :current-page="pickPage"
        :page-size="pickLimit"
        :total="pickTotal"
        small
        layout="total, prev, pager, next"
        style="margin-top: 12px"
        @current-change="(p) => { pickPage = p; loadAvailable(); }"
      />
    </el-dialog>

    <!-- 本店商品设置 -->
    <el-dialog v-model="vpEditVisible" title="本店商品设置" width="440px" destroy-on-close>
      <el-form label-width="110px">
        <el-form-item label="本店分类">
          <el-select v-model="vpEdit.shop_category_id" clearable placeholder="未分组" style="width: 100%">
            <el-option v-for="c in categoryOptionsAll" :key="c.id" :label="c.name + (c.status === 0 ? '（已停用）' : '')" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="vpEdit.sort_order" :min="0" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="vpEdit.status">
            <el-radio :value="1">在售</el-radio>
            <el-radio :value="0">下架</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="vpEditVisible = false">取消</el-button>
        <el-button type="primary" :loading="vpEditLoading" @click="submitVpEdit">保存</el-button>
      </template>
    </el-dialog>

    <!-- 售价库存 -->
    <el-drawer v-model="stockDrawerVisible" :title="`售价与库存 — ${stockTitle}`" size="520px" destroy-on-close>
      <el-table :data="stockRows" border size="small">
        <el-table-column label="规格" min-width="120">
          <template #default="{ row }">{{ row.product_sku?.spec_label ?? row.spec_label ?? '-' }}</template>
        </el-table-column>
        <el-table-column label="售价(元)" width="120">
          <template #default="{ row }">
            <el-input-number v-model="row.price" :min="0" :precision="2" controls-position="right" size="small" style="width: 100%" />
          </template>
        </el-table-column>
        <el-table-column label="库存" width="100">
          <template #default="{ row }">
            <el-input-number v-model="row.stock" :min="0" :precision="0" controls-position="right" size="small" style="width: 100%" />
          </template>
        </el-table-column>
        <el-table-column label="可售" width="80">
          <template #default="{ row }">
            <el-switch v-model="row.status" :active-value="1" :inactive-value="0" />
          </template>
        </el-table-column>
      </el-table>
      <div class="drawer-footer">
        <el-button type="primary" :loading="stockSaveLoading" @click="saveStockBatch">保存</el-button>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useUserStore } from '@/stores/user'
import {
  getVenueOptions,
  getVenueShopCategories,
  createVenueShopCategory,
  updateVenueShopCategory,
  deleteVenueShopCategory,
  getVenueShopProducts,
  getVenueShopAvailableProducts,
  addProductToVenue,
  updateVenueShopProduct,
  removeVenueShopProduct,
  batchUpdateVenueShopSkus,
  syncVenueShopSkus,
} from '@/api/shop'

const userStore = useUserStore()
const canVenue = computed(() => {
  const p = userStore.user?.permissions
  if (!Array.isArray(p)) return false
  return p.includes('*') || p.includes('admin.shop.venue.manage')
})

const venueOptions = ref([])
const venueId = ref(null)
const currentVenueName = computed(() => venueOptions.value.find((v) => v.id === venueId.value)?.name ?? '')
const categoryOptions = ref([])
/** 设置页下拉：含停用项，避免已选分类被隐藏 */
const categoryOptionsAll = ref([])
const filterCategoryId = ref(null)
const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

function imgUrl(u) {
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u
  return import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + u : u
}

async function loadVenues() {
  try {
    const res = await getVenueOptions()
    venueOptions.value = res.data?.list ?? []
  } catch (_) {
    venueOptions.value = []
  }
}

async function refreshCategoryOptions() {
  if (!venueId.value) {
    categoryOptions.value = []
    return
  }
  try {
    const res = await getVenueShopCategories(venueId.value)
    const raw = res.data?.list ?? []
    categoryOptions.value = raw.filter((c) => c.status === 1)
  } catch (_) {
    categoryOptions.value = []
  }
}

function onVenueChange() {
  page.value = 1
  list.value = []
  total.value = 0
  filterCategoryId.value = null
  if (venueId.value) {
    refreshCategoryOptions()
    fetchList()
  }
}

function onFilterCategory() {
  page.value = 1
  fetchList()
}

async function fetchList() {
  if (!venueId.value) return
  loading.value = true
  try {
    const params = { page: page.value, limit: limit.value }
    if (filterCategoryId.value !== null && filterCategoryId.value !== '') {
      params.shop_category_id = filterCategoryId.value
    }
    const res = await getVenueShopProducts(venueId.value, params)
    list.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

/* 本店分类弹窗 */
const catVisible = ref(false)
const catListLoading = ref(false)
const catList = ref([])
const catNewRows = ref([])
const savingCatKey = ref(null)
const catDisplayList = computed(() => [...catList.value, ...catNewRows.value])

function getCatRowKey(row) {
  return row._new ? String(row._tid) : String(row.id)
}

function openCategoryDialog() {
  if (!venueId.value) return
  catVisible.value = true
}

async function onCategoryDialogOpen() {
  await fetchCatList()
}

function closeCategoryDialog() {
  catList.value = []
  catNewRows.value = []
}

async function fetchCatList() {
  if (!venueId.value) return
  catListLoading.value = true
  try {
    const res = await getVenueShopCategories(venueId.value)
    const raw = res.data?.list ?? []
    catList.value = raw.map((r) => ({
      ...r,
      sort_order: Number(r.sort_order) || 0,
      status: r.status === 0 ? 0 : 1,
    }))
  } catch (_) {
    catList.value = []
  } finally {
    catListLoading.value = false
  }
}

function addCatRow() {
  catNewRows.value.push({
    _new: true,
    _tid: 'c_' + Date.now(),
    name: '',
    sort_order: catDisplayList.value.length,
    status: 1,
  })
}

async function saveCatRow(row) {
  if (!(row.name && String(row.name).trim())) {
    ElMessage.warning('请填写分类名称')
    return
  }
  savingCatKey.value = getCatRowKey(row)
  try {
    if (row._new) {
      await createVenueShopCategory(venueId.value, {
        name: String(row.name).trim(),
        sort_order: Number(row.sort_order) || 0,
        status: row.status === 0 ? 0 : 1,
      })
      ElMessage.success('添加成功')
      catNewRows.value = catNewRows.value.filter((r) => r !== row)
    } else {
      await updateVenueShopCategory(venueId.value, row.id, {
        name: String(row.name).trim(),
        sort_order: Number(row.sort_order) || 0,
        status: row.status === 0 ? 0 : 1,
      })
      ElMessage.success('保存成功')
    }
    await fetchCatList()
    await refreshCategoryOptions()
    fetchList()
  } catch (_) {}
  finally {
    savingCatKey.value = null
  }
}

function deleteCatRow(row) {
  if (row._new) {
    catNewRows.value = catNewRows.value.filter((r) => r !== row)
    return
  }
  ElMessageBox.confirm(`确定删除分类「${row.name}」？店内商品将变为未分组。`, '提示', { type: 'warning' })
    .then(async () => {
      await deleteVenueShopCategory(venueId.value, row.id)
      ElMessage.success('已删除')
      fetchCatList()
      refreshCategoryOptions()
      fetchList()
    })
    .catch(() => {})
}

/* pick */
const pickVisible = ref(false)
const pickLoading = ref(false)
const pickList = ref([])
const pickTotal = ref(0)
const pickPage = ref(1)
const pickLimit = ref(10)
const pickKeyword = ref('')
const pickShopCategoryId = ref(null)

function openPickDialog() {
  if (!venueId.value) return
  pickKeyword.value = ''
  pickPage.value = 1
  pickShopCategoryId.value = null
  pickVisible.value = true
}

async function onPickOpen() {
  await refreshCategoryOptions()
  loadAvailable()
}

async function loadAvailable() {
  if (!venueId.value) return
  pickLoading.value = true
  try {
    const params = { page: pickPage.value, limit: pickLimit.value }
    if (pickKeyword.value.trim()) params.keyword = pickKeyword.value.trim()
    const res = await getVenueShopAvailableProducts(venueId.value, params)
    pickList.value = res.data?.list ?? []
    pickTotal.value = res.data?.total ?? 0
  } finally {
    pickLoading.value = false
  }
}

async function addOne(row) {
  try {
    const payload = { product_id: row.id }
    if (pickShopCategoryId.value) {
      payload.shop_category_id = pickShopCategoryId.value
    }
    await addProductToVenue(venueId.value, payload)
    ElMessage.success('已加入店铺')
    pickVisible.value = false
    fetchList()
  } catch (_) {}
}

/* vp edit */
const vpEditVisible = ref(false)
const vpEditLoading = ref(false)
const vpEdit = reactive({ vp_id: 0, sort_order: 0, status: 1, shop_category_id: null })

async function openVpEdit(row) {
  vpEdit.vp_id = row.id
  vpEdit.sort_order = row.sort_order ?? 0
  vpEdit.status = row.status ?? 1
  vpEdit.shop_category_id = row.shop_category?.id ?? null
  try {
    const res = await getVenueShopCategories(venueId.value)
    categoryOptionsAll.value = res.data?.list ?? []
  } catch (_) {
    categoryOptionsAll.value = []
  }
  vpEditVisible.value = true
}

async function submitVpEdit() {
  vpEditLoading.value = true
  try {
    const body = {
      sort_order: vpEdit.sort_order,
      status: vpEdit.status,
      shop_category_id: vpEdit.shop_category_id ?? '',
    }
    await updateVenueShopProduct(venueId.value, vpEdit.vp_id, body)
    ElMessage.success('已保存')
    vpEditVisible.value = false
    fetchList()
  } finally {
    vpEditLoading.value = false
  }
}

function onRemove(row) {
  ElMessageBox.confirm(`从本店移除「${row.product?.name ?? ''}」？`, '提示', { type: 'warning' })
    .then(async () => {
      await removeVenueShopProduct(venueId.value, row.id)
      ElMessage.success('已移除')
      fetchList()
    })
    .catch(() => {})
}

async function onSyncSkus(row) {
  try {
    const res = await syncVenueShopSkus(venueId.value, row.id)
    ElMessage.success(res.msg || '同步完成')
    fetchList()
  } catch (_) {}
}

const stockDrawerVisible = ref(false)
const stockTitle = ref('')
const stockSaveLoading = ref(false)
const stockRows = ref([])

function openStockDrawer(row) {
  stockTitle.value = row.product?.name ?? ''
  stockRows.value = (row.skus ?? []).map((s) => ({
    id: s.id,
    price: Number(s.price ?? 0),
    stock: Number(s.stock ?? 0),
    status: s.status === 0 ? 0 : 1,
    product_sku: s.product_sku,
    spec_label: s.product_sku?.spec_label,
  }))
  stockDrawerVisible.value = true
}

async function saveStockBatch() {
  const items = stockRows.value.map((r) => ({
    id: r.id,
    price: r.price,
    stock: r.stock,
    status: r.status,
  }))
  stockSaveLoading.value = true
  try {
    await batchUpdateVenueShopSkus(venueId.value, items)
    ElMessage.success('已保存')
    stockDrawerVisible.value = false
    fetchList()
  } finally {
    stockSaveLoading.value = false
  }
}

onMounted(() => {
  loadVenues()
})
</script>

<style scoped>
.page {
  width: 100%;
  max-width: none;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}
.filter-form {
  margin-bottom: 12px;
}
.prod-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}
.prod-thumb {
  width: 44px;
  height: 44px;
  border-radius: 4px;
  flex-shrink: 0;
}
.drawer-footer {
  margin-top: 20px;
  text-align: right;
}
.spec-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.spec-tip {
  font-size: 13px;
  color: var(--el-text-color-secondary);
}
</style>
