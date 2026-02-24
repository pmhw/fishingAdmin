<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>管理员列表</span>
          <el-button type="primary" @click="openEdit()">新增管理员</el-button>
        </div>
      </template>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="username" label="账号" width="120" />
        <el-table-column prop="nickname" label="昵称" width="120" />
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '正常' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="last_login_at" label="最后登录" width="180" />
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" fixed="right" width="160">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" :disabled="row.id === currentUserId" @click="onDelete(row)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑管理员' : '新增管理员'" width="420px" @close="resetForm">
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="80px">
        <el-form-item label="账号" prop="username">
          <el-input v-model="editForm.username" placeholder="登录账号" :disabled="!!editId" />
        </el-form-item>
        <el-form-item v-if="!editId" label="密码" prop="password">
          <el-input v-model="editForm.password" type="password" placeholder="至少6位" show-password />
        </el-form-item>
        <el-form-item v-if="editId" label="新密码">
          <el-input v-model="editForm.password" type="password" placeholder="不修改请留空" show-password />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="editForm.nickname" placeholder="昵称" />
        </el-form-item>
        <el-form-item v-if="editId" label="状态" prop="status">
          <el-select v-model="editForm.status" placeholder="状态" style="width: 100%">
            <el-option :value="1" label="正常" />
            <el-option :value="0" label="禁用" />
          </el-select>
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
import { useUserStore } from '@/stores/user'
import { getAdminList, getAdminDetail, createAdmin, updateAdmin, deleteAdmin } from '@/api/adminUser'

const userStore = useUserStore()
const currentUserId = computed(() => userStore.user?.id)

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
  username: '',
  password: '',
  nickname: '',
  status: 1,
})
const editRules = computed(() => ({
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: editId.value
    ? [{ min: 6, message: '至少6位', trigger: 'blur' }]
    : [
        { required: true, message: '请输入密码', trigger: 'blur' },
        { min: 6, message: '至少6位', trigger: 'blur' },
      ],
}))

async function fetchList() {
  loading.value = true
  try {
    const res = await getAdminList({ page: page.value, limit: limit.value })
    list.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editId.value = row?.id ?? null
  editForm.username = row?.username ?? ''
  editForm.password = ''
  editForm.nickname = row?.nickname ?? ''
  editForm.status = row?.status ?? 1
  dialogVisible.value = true
  if (editId.value) {
    getAdminDetail(editId.value).then((res) => {
      editForm.nickname = res.data?.nickname ?? ''
      editForm.status = res.data?.status ?? 1
    })
  }
}

function resetForm() {
  editForm.username = ''
  editForm.password = ''
  editForm.nickname = ''
  editForm.status = 1
  editFormRef.value?.resetFields?.()
}

async function submitEdit() {
  if (editId.value) {
    await editFormRef.value?.validate().catch(() => {})
    const payload = { nickname: editForm.nickname, status: editForm.status }
    if (editForm.password) payload.password = editForm.password
    await updateAdmin(editId.value, payload)
    ElMessage.success('更新成功')
  } else {
    await editFormRef.value?.validate().catch(() => {})
    await createAdmin({
      username: editForm.username,
      password: editForm.password,
      nickname: editForm.nickname,
    })
    ElMessage.success('创建成功')
  }
  dialogVisible.value = false
  fetchList()
}

function onDelete(row) {
  ElMessageBox.confirm('确定删除该管理员？', '提示', {
    type: 'warning',
  }).then(async () => {
    await deleteAdmin(row.id)
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
</style>
