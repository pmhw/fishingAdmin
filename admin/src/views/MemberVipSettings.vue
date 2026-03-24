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
      <el-form label-width="180px" style="max-width: 640px" @submit.prevent>
        <el-form-item label="充值档位（元）">
          <el-select
            v-model="packageTags"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="输入数字后回车添加，如 100、200"
            style="width: 100%"
          />
          <div class="form-tip">仅允许用户选择此处列出的金额发起充值；留空表示暂不开放充值。</div>
        </el-form-item>
        <el-form-item label="单笔满额升级 VIP（元）">
          <el-input-number v-model="thresholdYuan" :min="0" :precision="2" :step="10" style="width: 200px" />
          <div class="form-tip">填 0 表示仅充值不加 VIP；例如填 500 表示单次实付 ≥500 元时自动成为会员。</div>
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
const thresholdYuan = ref(0)

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
.form-tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  line-height: 1.5;
  margin-top: 6px;
}
code {
  font-size: 12px;
  padding: 0 4px;
  background: var(--el-fill-color-light);
  border-radius: 4px;
}
</style>
