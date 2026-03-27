# 小程序端开发说明：回鱼打款（用户确认收款模式）

本文面向小程序前端，说明如何让用户在微信内确认收款（`wx.requestMerchantTransfer`）。

---

## 1. 业务模式

- **会员用户**：后台点击打款后，金额直接入 `mini_user.balance`，小程序无需确认。
- **非会员用户**：后台点击打款后，状态变为 `pending`，用户需在小程序端调用 `wx.requestMerchantTransfer` 确认收款。

---

## 2. 接口清单（小程序侧）

### 2.1 我的回鱼打款列表

- **方法**：`GET`
- **路径**：`/api/mini/return-payouts`
- **鉴权**：需要 `Authorization: Bearer <token>`

可选 query：

- `page`、`limit`
- `payout_status`：`none/pending/success/failed/cancelled`
- `payout_channel`：`balance/wechat`

返回字段中包含：

- `can_confirm`：`1/0`，当 `payout_status=pending` 且 `payout_channel=wechat` 时为 1，可显示“去确认收款”按钮。

---

### 2.2 获取确认收款参数

- **方法**：`GET`
- **路径**：`/api/mini/return-payouts/:id/package`
- **鉴权**：需要 `Authorization: Bearer <token>`

其中 `:id` 为回鱼流水 `pond_return_log.id`。

### 成功返回示例

```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "mchId": "1900000001",
    "appId": "wxxxxxxxxxxxxxxx",
    "package": "affffddafdfafddffda==",
    "openId": "o-MYE42l80oelYMDE34nYD456Xoy"
  }
}
```

### 常见错误

- `403 无权访问`：该回鱼流水不属于当前登录用户的开钓单
- `400 该记录不是微信打款`：会员入余额不需要确认
- `400 该记录未处于待确认状态`：不是 `pending`，可能已成功或失败
- `400 缺少 package_info`：后台发起失败/未正确返回，需管理员重发

---

## 3. 小程序拉起确认收款

后端返回参数后，按微信官方调用：

```js
if (wx.canIUse('requestMerchantTransfer')) {
  wx.requestMerchantTransfer({
    mchId: data.mchId,
    appId: data.appId,
    package: data.package,
    openId: data.openId,
    success: (res) => {
      // 注意：这里只表示拉起/确认页流程成功返回，不代表最终一定到账
      console.log('requestMerchantTransfer success', res)
    },
    fail: (err) => {
      console.log('requestMerchantTransfer fail', err)
    },
  })
} else {
  wx.showModal({
    content: '你的微信版本过低，请更新到最新版本',
    showCancel: false,
  })
}
```

---

## 4. 推荐前端流程

1. 用户进入「回鱼打款待确认」页面（可由你们自定义页面）。
2. 列表中仅展示 `payout_status = pending` 且 `payout_channel = wechat` 的记录（需要你们后续补一个小程序查询接口或复用已有接口）。
3. 用户点击“确认收款”：
   - 调 `GET /api/mini/return-payouts/:id/package`
   - 成功后调用 `wx.requestMerchantTransfer`
4. 用户返回后刷新列表状态。

---

## 5. 重要注意事项

1. `wx.requestMerchantTransfer` 的 `success` **不等于**最终打款成功，最终状态以微信回调/查询为准。
2. 当前后端已支持“拉起确认页参数获取”，若要状态闭环，建议再补：
   - 微信商家转账回调落库（`pending -> success/failed/cancelled`）
   - 小程序“我的回鱼打款记录”查询接口
3. 调用前请确认商户平台已开通「商家转账」并配置请求来源 IP。

---

## 6. 相关后端代码位置

- 小程序取 package：`app/controller/Api/Mini/ReturnPayoutController.php`
- 后台发起打款：`app/controller/Api/Admin/PondReturnLogController.php` -> `payout`
- 打款业务：`app/service/ReturnLogPayoutService.php`
- 微信 v3 请求签名：`app/service/WechatPayV3Client.php`

