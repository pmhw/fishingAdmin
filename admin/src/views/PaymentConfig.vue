<template>
  <div class="page">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>支付配置</span>
          <div class="actions">
            <el-button :loading="loading" @click="fetchData">刷新</el-button>
            <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
          </div>
        </div>
      </template>

      <el-alert type="info" :closable="false" show-icon style="margin-bottom: 12px">
        本页保存后会写入数据库表 <code>system_config</code>（与「全局配置」同源），现有支付/回鱼打款逻辑将自动读取生效。
      </el-alert>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="160px">
        <div class="section-title">微信支付（小程序支付 / v2）</div>
        <el-form-item label="微信支付回调地址" prop="pay_notify_url">
          <el-input v-model="form.pay_notify_url" placeholder="如 https://你的域名/api/mini/pay/notify" />
          <div class="tip">对应 system_config：<code>pay_notify_url</code></div>
        </el-form-item>
        <el-form-item label="微信支付商户号" prop="pay_mch_id">
          <el-input v-model="form.pay_mch_id" placeholder="商户号 mch_id" />
          <div class="tip">对应 system_config：<code>pay_mch_id</code></div>
        </el-form-item>
        <el-form-item label="商户 APIv2 密钥" prop="pay_key">
          <el-input v-model="form.pay_key" show-password placeholder="APIv2 Key" />
          <div class="tip">对应 system_config：<code>pay_key</code></div>
        </el-form-item>

        <div class="section-title">小程序基础信息</div>
        <el-form-item label="小程序 AppID" prop="mini_appid">
          <el-input v-model="form.mini_appid" placeholder="wx..." />
          <div class="tip">对应 system_config：<code>mini_appid</code></div>
        </el-form-item>
        <el-form-item label="小程序 AppSecret" prop="mini_secret">
          <el-input v-model="form.mini_secret" show-password placeholder="AppSecret" />
          <div class="tip">对应 system_config：<code>mini_secret</code></div>
        </el-form-item>

        <div class="section-title">微信 v3 回鱼转账（商家转账用户确认模式）</div>
        <el-form-item label="v3 证书序列号" prop="wxpay_v3_serial_no">
          <el-input v-model="form.wxpay_v3_serial_no" placeholder="如 444F486D..." />
          <div class="tip">对应 system_config：<code>wxpay_v3_serial_no</code></div>
        </el-form-item>
        <el-form-item label="v3 商户私钥 PEM" prop="wxpay_v3_private_key_pem">
          <el-input
            v-model="form.wxpay_v3_private_key_pem"
            type="textarea"
            :rows="6"
            placeholder="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
          />
          <div class="tip">对应 system_config：<code>wxpay_v3_private_key_pem</code></div>
        </el-form-item>
        <el-form-item label="v3 转账小程序 AppID" prop="wxpay_v3_appid">
          <el-input v-model="form.wxpay_v3_appid" placeholder="wx..." />
          <div class="tip">对应 system_config：<code>wxpay_v3_appid</code></div>
        </el-form-item>
        <el-form-item label="v3 转账回调地址" prop="wxpay_v3_transfer_notify_url">
          <el-input v-model="form.wxpay_v3_transfer_notify_url" placeholder="如 https://你的域名/api/wechat/transfer/notify" />
          <div class="tip">对应 system_config：<code>wxpay_v3_transfer_notify_url</code>（可选）</div>
        </el-form-item>
        <el-form-item label="v3 转账场景 ID" prop="wxpay_v3_transfer_scene_id">
          <el-input v-model="form.wxpay_v3_transfer_scene_id" placeholder="默认 1000" />
          <div class="tip">对应 system_config：<code>wxpay_v3_transfer_scene_id</code>（可选）</div>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getPaymentConfig, savePaymentConfig } from '@/api/paymentConfig'

const loading = ref(false)
const saving = ref(false)
const formRef = ref(null)

const form = reactive({
  pay_notify_url: '',
  pay_key: '',
  pay_mch_id: '',
  mini_appid: '',
  mini_secret: '',
  wxpay_v3_serial_no: '',
  wxpay_v3_private_key_pem: '',
  wxpay_v3_appid: '',
  wxpay_v3_transfer_notify_url: '',
  wxpay_v3_transfer_scene_id: '',
})

const rules = {
  pay_notify_url: [{ required: false }],
  pay_key: [{ required: false }],
  pay_mch_id: [{ required: false }],
  mini_appid: [{ required: false }],
  mini_secret: [{ required: false }],
  wxpay_v3_serial_no: [{ required: false }],
  wxpay_v3_private_key_pem: [{ required: false }],
  wxpay_v3_appid: [{ required: false }],
}

async function fetchData() {
  loading.value = true
  try {
    const res = await getPaymentConfig()
    const d = res?.data || {}
    Object.keys(form).forEach((k) => {
      if (d[k] != null) form[k] = String(d[k])
    })
  } finally {
    loading.value = false
  }
}

async function onSave() {
  await formRef.value?.validate?.().catch(() => {})
  saving.value = true
  try {
    await savePaymentConfig({ ...form })
    ElMessage.success('保存成功')
    fetchData()
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.actions {
  display: flex;
  gap: 10px;
}
.section-title {
  margin: 14px 0 8px;
  font-weight: 700;
  color: #0f172a;
}
.tip {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  margin-top: 6px;
}
code {
  background: var(--el-fill-color-light);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
}
</style>

