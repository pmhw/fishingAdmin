# 回鱼流水打款（会员余额 / 微信转账 v3）

## 1. 需求规则

- **会员（mini_user.is_vip=1）**：回鱼金额直接 **入账到 `mini_user.balance`**，并在回鱼流水上标记打款成功。
- **非会员（is_vip=0）**：调用微信支付 v3 **「转账到零钱」**接口发起转账；回鱼流水标记为 `pending`，等待后续补回调确认（本期先记录请求结果，回调可后续加）。

## 2. 数据库迁移

执行：`md/pond_return_log_payout_fields.sql`

会给 `pond_return_log` 增加以下字段（节选）：

- `payout_status`：none / pending / success / failed / cancelled
- `payout_channel`：balance / wechat
- `payout_amount`：实际打款金额（默认等于 amount）
- `payout_out_bill_no`：微信 `out_bill_no`
- `payout_time`：成功时间
- `payout_fail_reason` / `payout_raw`：失败原因、请求/返回原文

## 3. 后台接口

### 3.1 发起打款

`POST /api/admin/pond-return-logs/:id/payout`

- **会员**：立即入余额并返回成功。
- **非会员**：调用微信 v3 发起转账，成功则返回 `pending` + `out_bill_no`。

### 3.2 列表增强

`GET /api/admin/pond-return-logs` 列表每条多返回：

- `is_vip_user`：0/1（用于前端决定按钮文案：入余额/微信转账）

## 4. 管理端页面

页面：`admin/src/views/ReturnLogList.vue`

- 新增列：打款状态、打款方式、打款时间
- 新增按钮：`入余额`（会员）/ `微信转账`（非会员）
  - 仅在 `payout_status` 为 `none/failed` 时显示

## 5. 微信 v3 转账配置（system_config）

通过后台「全局配置」或直接写 `system_config` 配置以下 key：

- `pay_mch_id`：商户号（与现有微信支付 v2 复用）
- `wxpay_v3_serial_no`：商户证书序列号（Authorization 里的 `serial_no`）
- `wxpay_v3_private_key_pem`：商户私钥 PEM（PKCS#8，包含 `-----BEGIN PRIVATE KEY-----`）
- `wxpay_v3_appid`：小程序 appid（收款 openid 所属）

可选：

- `wxpay_v3_transfer_notify_url`：转账回调地址（后续可实现回调更新 `payout_status`）
- `wxpay_v3_transfer_scene_id`：场景 id（默认 `1000`）

## 6. 已知限制 / 后续事项

1. **本期未实现回调验签与落库**：目前把发起结果写入 `payout_raw`，并将状态置为 `pending`；建议后续补 `/api/mini/...` 或 `/api/admin/...` 的回调入口，将 `pending -> success/failed`。
2. **未传 user_name**：v3 转账敏感信息加密需要平台证书，本期默认只用 openid；如你必须校验姓名，可再补平台证书下载、RSA 加密与 `user_name` 字段。

