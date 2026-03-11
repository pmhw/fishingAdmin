<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>全局配置</span>
          <el-button type="primary" @click="openEdit()">新增变量</el-button>
        </div>
      </template>
      <el-alert
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 12px"
      >
        变量名(key) 用于在代码中通过 <code>get_config('key')</code> 或 <code>SystemConfig::getValue('key')</code> 获取。添加后禁止在后台删除，仅支持编辑；删除请直接在数据库中操作。
      </el-alert>
      <el-table v-loading="loading" :data="list" stripe>
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="config_key" label="变量(key)" min-width="160" show-overflow-tooltip />
        <el-table-column label="值(value)" min-width="220">
          <template #default="{ row }">
            <span>{{ showValueMap[row.id] ? (row.config_value || '-') : '********' }}</span>
            <el-button link type="primary" @click="toggleShowValue(row)">
              <el-icon>
                <View v-if="!showValueMap[row.id]" />
                <Hide v-else />
              </el-icon>
            </el-button>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
        <el-table-column prop="updated_at" label="更新时间" width="170" />
        <el-table-column label="操作" fixed="right" width="100">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
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

    <el-dialog v-model="dialogVisible" :title="editId ? '编辑配置' : '新增配置'" width="500px" @close="resetForm">
      <el-form ref="editFormRef" :model="editForm" :rules="editRules" label-width="90px">
        <el-form-item label="变量(key)" prop="config_key">
          <el-input
            v-model="editForm.config_key"
            placeholder="英文/数字，如 site_name、api_limit"
            :disabled="!!editId"
          />
          <div v-if="editId" class="form-tip">变量名创建后不可修改</div>
        </el-form-item>
        <el-form-item label="值(value)">
          <el-input v-model="editForm.config_value" type="textarea" :rows="3" placeholder="配置值，可为空" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="editForm.remark" placeholder="选填，说明该变量用途" />
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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { View, Hide } from '@element-plus/icons-vue'
import { getConfigList, getConfigDetail, createConfig, updateConfig } from '@/api/config'

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
  config_key: '',
  config_value: '',
  remark: '',
})
const editRules = {
  config_key: [{ required: true, message: '请输入变量名', trigger: 'blur' }],
}

// 是否显示某条配置的真实值，默认全部隐藏，用 id 做 key
const showValueMap = ref({})

function toggleShowValue(row) {
  const id = row?.id
  if (!id) return
  showValueMap.value[id] = !showValueMap.value[id]
}

async function fetchList() {
  loading.value = true
  try {
    const res = await getConfigList({ page: page.value, limit: limit.value })
    list.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function openEdit(row) {
  editId.value = row?.id ?? null
  editForm.config_key = row?.config_key ?? ''
  editForm.config_value = row?.config_value ?? ''
  editForm.remark = row?.remark ?? ''
  dialogVisible.value = true
  if (editId.value) {
    getConfigDetail(editId.value).then((res) => {
      const d = res.data
      editForm.config_key = d?.config_key ?? ''
      editForm.config_value = d?.config_value ?? ''
      editForm.remark = d?.remark ?? ''
    })
  }
}

function resetForm() {
  editForm.config_key = ''
  editForm.config_value = ''
  editForm.remark = ''
  editFormRef.value?.resetFields?.()
}

async function submitEdit() {
  await editFormRef.value?.validate().catch(() => {})
  submitLoading.value = true
  try {
    if (editId.value) {
      await updateConfig(editId.value, {
        config_value: editForm.config_value,
        remark: editForm.remark,
      })
      ElMessage.success('更新成功')
    } else {
      await createConfig({
        config_key: editForm.config_key,
        config_value: editForm.config_value,
        remark: editForm.remark,
      })
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    fetchList()
  } finally {
    submitLoading.value = false
  }
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
.form-tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  margin-top: 4px;
}
code {
  background: var(--el-fill-color-light);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
}
</style>
