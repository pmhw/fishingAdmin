<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>轮播图列表</span>
          <el-button type="primary" @click="openEdit()">新增轮播图</el-button>
        </div>
      </template>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="图片" width="120">
          <template #default="{ row }">
            <el-image
              v-if="row.image_url"
              :src="getImageDisplayUrl(row.image_url)"
              :preview-src-list="[getImageDisplayUrl(row.image_url)]"
              fit="cover"
              style="width: 100px; height: 56px; border-radius: 4px; cursor: pointer"
            />
            <span v-else class="text-muted">未设置</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="120" show-overflow-tooltip />
        <el-table-column prop="link_url" label="跳转链接" min-width="150" show-overflow-tooltip />
        <el-table-column prop="sort" label="排序" width="80" />
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '展示' : '隐藏' }}</el-tag>
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

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑轮播图' : '新增轮播图'" width="480px" @close="resetForm">
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="90px">
        <el-form-item label="标题">
          <el-input v-model="editForm.title" placeholder="选填，用于后台区分" />
        </el-form-item>
        <el-form-item label="轮播图" prop="image_url">
          <div class="upload-wrap">
            <el-image
              v-if="editForm.image_url"
              :src="imageDisplayUrl"
              :preview-src-list="[imageDisplayUrl]"
              fit="cover"
              class="upload-preview"
            />
            <el-upload
              class="upload-btn"
              accept="image/jpeg,image/png,image/gif,image/webp"
              :show-file-list="false"
              :http-request="handleUpload"
            >
              <el-button type="primary" :loading="uploading">{{ editForm.image_url ? '更换图片' : '上传图片' }}</el-button>
            </el-upload>
            <span v-if="editForm.image_url" class="upload-tip">已选图片，可点击「更换图片」重新上传</span>
          </div>
        </el-form-item>
        <el-form-item label="跳转链接">
          <el-input v-model="editForm.link_url" placeholder="点击跳转的链接，选填" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort" :min="0" controls-position="right" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="editForm.status">
            <el-radio :value="1">展示</el-radio>
            <el-radio :value="0">隐藏</el-radio>
          </el-radio-group>
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
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getBannerList, getBannerDetail, createBanner, updateBanner, deleteBanner, uploadImage } from '@/api/banner'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const limit = ref(10)

const dialogVisible = ref(false)
const editId = ref(null)
const editFormRef = ref(null)
const submitLoading = ref(false)
const editForm = reactive({
  title: '',
  image_url: '',
  link_url: '',
  sort: 0,
  status: 1,
})
const editRules = {
  image_url: [{ required: true, message: '请上传图片', trigger: 'change' }],
}
const uploading = ref(false)
const imageDisplayUrl = computed(() => {
  const u = editForm.image_url
  if (!u) return ''
  if (/^https?:\/\//i.test(u)) return u
  return import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + u : u
})

function getImageDisplayUrl(url) {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  return import.meta.env.VITE_STORAGE_URL ? import.meta.env.VITE_STORAGE_URL + url : url
}

async function handleUpload({ file }) {
  if (!file) return
  const isImage = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)
  if (!isImage) {
    ElMessage.warning('请选择 jpg/png/gif/webp 图片')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    ElMessage.warning('图片不能超过 2MB')
    return
  }
  uploading.value = true
  try {
    const res = await uploadImage(file)
    editForm.image_url = res.data?.url ?? ''
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
    const res = await getBannerList({ page: page.value, limit: limit.value })
    list.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editId.value = row?.id ?? null
  editForm.title = row?.title ?? ''
  editForm.image_url = row?.image_url ?? ''
  editForm.link_url = row?.link_url ?? ''
  editForm.sort = row?.sort ?? 0
  editForm.status = row?.status ?? 1
  dialogVisible.value = true
  if (editId.value) {
    getBannerDetail(editId.value).then((res) => {
      const d = res.data
      editForm.title = d?.title ?? ''
      editForm.image_url = d?.image_url ?? ''
      editForm.link_url = d?.link_url ?? ''
      editForm.sort = d?.sort ?? 0
      editForm.status = d?.status ?? 1
    })
  }
}

function resetForm() {
  editForm.title = ''
  editForm.image_url = ''
  editForm.link_url = ''
  editForm.sort = 0
  editForm.status = 1
  editFormRef.value?.resetFields?.()
}

async function submitEdit() {
  await editFormRef.value?.validate().catch(() => {})
  submitLoading.value = true
  try {
    if (editId.value) {
      await updateBanner(editId.value, {
        title: editForm.title,
        image_url: editForm.image_url,
        link_url: editForm.link_url || undefined,
        sort: editForm.sort,
        status: editForm.status,
      })
      ElMessage.success('更新成功')
    } else {
      await createBanner({
        title: editForm.title,
        image_url: editForm.image_url,
        link_url: editForm.link_url || undefined,
        sort: editForm.sort,
        status: editForm.status,
      })
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    fetchList()
  } finally {
    submitLoading.value = false
  }
}

function onDelete(row) {
  ElMessageBox.confirm('确定删除该轮播图？', '提示', {
    type: 'warning',
  }).then(async () => {
    await deleteBanner(row.id)
    ElMessage.success('已删除')
    fetchList()
  }).catch(() => {})
}

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
.text-muted {
  color: var(--el-text-color-secondary);
  font-size: 12px;
}
.upload-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.upload-preview {
  width: 200px;
  height: 112px;
  border-radius: 8px;
  border: 1px solid var(--el-border-color);
  cursor: pointer;
}
.upload-tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
</style>
