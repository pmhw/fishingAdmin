<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>角色与权限</span>
          <el-button type="primary" @click="openEdit()">新增角色</el-button>
        </div>
      </template>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="name" label="角色名" width="140" />
        <el-table-column prop="code" label="编码" width="140" />
        <el-table-column prop="description" label="描述" min-width="200" />
        <el-table-column label="操作" fixed="right" width="160">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑角色' : '新增角色'" width="560px" @close="resetForm">
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="80px">
        <el-form-item label="角色名" prop="name">
          <el-input v-model="editForm.name" placeholder="如：运营管理员" />
        </el-form-item>
        <el-form-item label="编码" prop="code">
          <el-input v-model="editForm.code" placeholder="如：operator" :disabled="!!editId" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="editForm.description" type="textarea" placeholder="角色说明" :rows="2" />
        </el-form-item>
        <el-form-item label="权限">
          <div class="permission-groups">
            <div v-for="(perms, moduleName) in permissionGroups" :key="moduleName" class="permission-group">
              <div class="group-title">{{ getModuleLabel(moduleName) }}</div>
              <el-checkbox-group v-model="editForm.permission_ids" class="permission-list">
                <el-checkbox v-for="p in perms" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</el-checkbox>
              </el-checkbox-group>
            </div>
          </div>
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
import { getRoleList, getRoleDetail, createRole, updateRole, deleteRole, getPermissionList } from '@/api/role'

const loading = ref(false)
const list = ref([])
const permissionGroups = ref({})

const MODULE_LABELS = {
  user: '管理员',
  system: '系统',
  content: '内容管理',
  misc: '杂项',
  other: '其他',
}

function getModuleLabel(key) {
  return MODULE_LABELS[key] || key
}

const dialogVisible = ref(false)
const editId = ref(null)
const editFormRef = ref(null)
const submitLoading = ref(false)
const editForm = reactive({
  name: '',
  code: '',
  description: '',
  permission_ids: [],
})
const editRules = {
  name: [{ required: true, message: '请输入角色名', trigger: 'blur' }],
  code: [{ required: true, message: '请输入编码', trigger: 'blur' }],
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getRoleList()
    list.value = res.data ?? []
  } finally {
    loading.value = false
  }
}

async function loadPermissions() {
  const res = await getPermissionList()
  permissionGroups.value = res.data ?? {}
}

function openEdit(row) {
  editId.value = row?.id ?? null
  editForm.name = row?.name ?? ''
  editForm.code = row?.code ?? ''
  editForm.description = row?.description ?? ''
  editForm.permission_ids = row?.permission_ids ? [...row.permission_ids] : []
  dialogVisible.value = true
  if (editId.value) {
    getRoleDetail(editId.value).then((res) => {
      const d = res.data
      editForm.name = d?.name ?? ''
      editForm.code = d?.code ?? ''
      editForm.description = d?.description ?? ''
      editForm.permission_ids = Array.isArray(d?.permission_ids) ? [...d.permission_ids] : []
    })
  }
}

function resetForm() {
  editForm.name = ''
  editForm.code = ''
  editForm.description = ''
  editForm.permission_ids = []
  editFormRef.value?.resetFields?.()
}

async function submitEdit() {
  await editFormRef.value?.validate().catch(() => {})
  submitLoading.value = true
  try {
    if (editId.value) {
      await updateRole(editId.value, {
        name: editForm.name,
        description: editForm.description,
        permission_ids: editForm.permission_ids,
      })
      ElMessage.success('更新成功')
    } else {
      await createRole({
        name: editForm.name,
        code: editForm.code,
        description: editForm.description,
        permission_ids: editForm.permission_ids,
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
  ElMessageBox.confirm('确定删除该角色？', '提示', {
    type: 'warning',
  }).then(async () => {
    await deleteRole(row.id)
    ElMessage.success('已删除')
    fetchList()
  }).catch(() => {})
}

onMounted(() => {
  loadPermissions()
  fetchList()
})
</script>
<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.permission-groups {
  max-height: 320px;
  overflow-y: auto;
  border: 1px solid var(--el-border-color);
  border-radius: 4px;
  padding: 12px;
}
.permission-group {
  margin-bottom: 12px;
}
.permission-group:last-child {
  margin-bottom: 0;
}
.group-title {
  font-weight: 600;
  color: var(--el-text-color-primary);
  margin-bottom: 8px;
}
.permission-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
}
.permission-list .el-checkbox {
  margin-right: 0;
}
</style>
