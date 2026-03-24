<template>
  <div class="page">
    <el-card v-loading="loading">
      <template #header>
        <span>会员充值与 VIP 升级</span>
      </template>
      <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px">
        配置小程序端可选充值档位（元）。用户微信支付成功后，账户余额按实付增加；若开启「单笔满额升级」，当该笔充值实付金额（分）不低于门槛时，将用户
        <code>is_vip</code> 置为 1。支付走现有
        <code>POST /api/mini/pay/wechat/jsapi</code>，订单描述固定为「会员余额充值」。
      </el-alert>
      <el-form label-width="180px" class="member-vip-form" @submit.prevent>
        <el-form-item label="充值档位（元）">
          <div class="field-stack">
            <div class="package-input-row">
              <el-input
                v-model="packageInput"
                clearable
                placeholder="输入金额后按回车添加，如 100"
                class="package-input"
                @keydown.enter.prevent="addPackageFromInput"
              />
              <el-button type="primary" plain @click="addPackageFromInput">添加</el-button>
            </div>
            <div v-if="packageTags.length" class="package-tags">
              <el-tag
                v-for="(t, i) in packageTags"
                :key="`${t}-${i}`"
                closable
                class="package-tag"
                @close="removePackage(i)"
              >
                {{ t }} 元
              </el-tag>
            </div>
            <div v-else class="form-tip form-tip--first">尚未添加档位。</div>
            <div class="form-tip">仅允许用户选择此处列出的金额发起充值；留空表示暂不开放充值。</div>
          </div>
        </el-form-item>
        <el-form-item label="单笔满额升级 VIP（元）">
          <div class="field-stack">
            <el-input-number v-model="thresholdYuan" :min="0" :precision="2" :step="10" class="threshold-input" />
            <div class="form-tip">填 0 表示仅充值不加 VIP；例如填 500 表示单次实付 ≥500 元时自动成为会员。</div>
          </div>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getMemberVipSettings, updateMemberVipSettings } from '@/api/memberVip'

const loading = ref(false)
const saving = ref(false)
const packageTags = ref([])
const packageInput = ref('')
const thresholdYuan = ref(0)

function addPackageFromInput() {
  const s = String(packageInput.value ?? '').trim()
  if (s === '') {
    return
  }
  const n = Number(s)
  if (!Number.isFinite(n) || n <= 0) {
    ElMessage.warning('请输入大于 0 的数字')
    return
  }
  const key = String(n)
  if (packageTags.value.includes(key)) {
    ElMessage.info('该档位已存在')
    packageInput.value = ''
    return
  }
  packageTags.value = [...packageTags.value, key].sort((a, b) => Number(a) - Number(b))
  packageInput.value = ''
}

function removePackage(index) {
  packageTags.value = packageTags.value.filter((_, i) => i !== index)
}

async function load() {
  loading.value = true
  try {
    const res = await getMemberVipSettings()
    const d = res?.data
    const pk = d?.packages_yuan
    packageTags.value = Array.isArray(pk) ? pk.map((x) => String(x)) : []
    thresholdYuan.value = Number(d?.vip_upgrade_threshold_yuan ?? 0)
  } catch (e) {
    ElMessage.error(e?.message || '加载失败')
  } finally {
    loading.value = false
  }
}

function parsePackages() {
  const nums = []
  for (const t of packageTags.value) {
    const s = String(t).trim()
    if (s === '') continue
    const n = Number(s)
    if (!Number.isFinite(n) || n <= 0) {
      throw new Error(`档位无效：${s}`)
    }
    nums.push(n)
  }
  return nums
}

async function onSave() {
  let packages_yuan
  try {
    packages_yuan = parsePackages()
  } catch (e) {
    ElMessage.warning(e.message || '档位格式错误')
    return
  }
  saving.value = true
  try {
    await updateMemberVipSettings({
      packages_yuan,
      vip_upgrade_threshold_yuan: thresholdYuan.value ?? 0,
    })
    ElMessage.success('已保存')
    await load()
  } catch (e) {
    ElMessage.error(e?.response?.data?.msg || e?.message || '保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
<style scoped>
.member-vip-form {
  max-width: 720px;
}
/* 表单项内容区默认是横向 flex，多个子节点会挤成一行；包一层纵向占满宽度 */
.field-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  min-width: 0;
  gap: 0;
}
.form-tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  line-height: 1.5;
  margin-top: 6px;
}
.form-tip--first {
  margin-top: 8px;
}
.package-input {
  flex: 1;
  min-width: 160px;
  max-width: 320px;
}
.threshold-input {
  width: 200px;
}
code {
  font-size: 12px;
  padding: 0 4px;
  background: var(--el-fill-color-light);
  border-radius: 4px;
}
.package-input-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.package-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.package-tag {
  margin-right: 0;
}
</style>
