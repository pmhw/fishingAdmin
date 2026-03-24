# 会员余额充值 · 前端开发说明

本文说明**管理后台**与**微信小程序**在「充值档位配置、余额充值、单笔满额升 VIP」相关的前端对接方式。服务端实现见 `MemberBalanceRechargeService`、`BalanceRechargeController`、`PayController::notify`。

---

## 一、业务简述

1. 运营在后台配置**可选充值金额（元）**与**单笔满额升级 VIP 门槛（元）**。
2. 小程序用户选择档位 → 服务端创建待支付订单 → 调起微信 JSAPI 支付。
3. 支付成功后，微信回调服务端：**增加 `mini_user.balance`**；若开启满额升级且**该笔实付（分）≥ 门槛**，则 **`is_vip = 1`**。
4. 充值订单在 `fishing_order` 中 **`description` 固定为「会员余额充值」**，金额单位为**分**；**不要**自行拼单号改金额，须以创建订单接口返回为准。

### 已是会员（`is_vip = 1`）时

- **余额**：与其它用户相同，按实付金额正常入账。
- **会员身份**：**不会降级**。满额逻辑仅是「满足条件则置 `is_vip = 1`」；用户本来就是会员时，再执行一次赋值仍为会员，无额外副作用。
- **小程序展示**：`GET /api/mini/user/recharge/options` 不区分是否已是会员。若不想对老会员展示「满 xx 元成为会员」，可在充值页先调 `GET /api/mini/user/balance`（或用户信息接口）拿到 `is_vip`，为 `true` 时隐藏或改写该文案，仅保留「充值档位」即可。

---

## 二、微信小程序

### 2.1 接口列表

| 方法 | 路径 | 鉴权 | 说明 |
|------|------|------|------|
| GET | `/api/mini/user/recharge/options` | 无 | 拉取档位与 VIP 规则（展示用） |
| POST | `/api/mini/user/recharge/order` | 需登录（Bearer token） | 创建充值待支付订单 |
| POST | `/api/mini/pay/wechat/jsapi` | 需登录 | 统一下单，返回 `wx.requestPayment` 参数 |
| GET | `/api/mini/user/balance` | 需登录 | 支付成功后刷新余额 / 会员状态 |

兼容路径：若项目里配置了别名，`/api/mini/pay/weixin/jsapi` 与 `jsapi` 等价（以 `route/app.php` 为准）。

### 2.2 GET `/api/mini/user/recharge/options`

**响应 `data` 示例：**

```json
{
  "packages_yuan": [100, 200, 500],
  "vip_upgrade_threshold_yuan": 500,
  "vip_upgrade_enabled": true
}
```

| 字段 | 说明 |
|------|------|
| `packages_yuan` | 后台配置的档位（元），升序；**空数组**表示未开放充值，不应让用户发起下单 |
| `vip_upgrade_threshold_yuan` | 单笔实付满该金额（元）则升 VIP；为 `0` 时不自动升 |
| `vip_upgrade_enabled` | `threshold > 0` 时为 `true`，便于 UI 展示「满 xx 元成为会员」 |

### 2.3 POST `/api/mini/user/recharge/order`

**请求体（JSON）：**

```json
{ "amount_yuan": 100 }
```

`amount_yuan` 必须与 `options` 里某一档一致（服务端按「元→分」校验）。

**成功 `data`：**

```json
{
  "order_no": "2025032512000012345678",
  "total_fee": 10000,
  "description": "会员余额充值"
}
```

| 字段 | 说明 |
|------|------|
| `order_no` | 业务单号，作为微信 `out_trade_no` |
| `total_fee` | **分**，传给 jsapi（见下） |
| `description` | 固定文案，传给 jsapi |

**常见错误：** `code !== 0` 时读 `msg`，例如「暂未开放余额充值」「充值金额不在可选档位」。

### 2.4 POST `/api/mini/pay/wechat/jsapi`

在拿到上一步的 `order_no` / `total_fee` / `description` 后调用（**须登录**）。

**推荐传参（充值场景）：**

```json
{
  "order_no": "<上一步返回>",
  "description": "会员余额充值",
  "total_fee": 10000
}
```

说明：当 `order_no` 已存在且为当前用户的 `pending` 订单时，服务端**以库里的金额与描述为准**，会忽略本次传入的 `total_fee`（防篡改）。仍建议传入与创建订单时一致，便于联调阅读。

**成功 `data`：** 与现有支付一致，内含 `timeStamp`、`nonceStr`、`package`、`signType`、`paySign` 等，直接用于 `wx.requestPayment`。

### 2.5 推荐页面流程（小程序）

1. 进入充值页 → `GET .../recharge/options` → 用 `packages_yuan` 渲染按钮；若为空，提示「暂未开放」。
2. 用户点某一档 → `POST .../recharge/order`，`amount_yuan` 为该档数值。
3. `POST .../pay/wechat/jsapi`，拉起 `wx.requestPayment`。
4. 支付结束（`success` / `fail` / `complete`）后：可轮询或引导用户下拉刷新，再调 `GET .../user/balance` 更新展示的余额与 `is_vip`。

**注意：** 是否支付成功以服务端订单状态为准；必要时可用已有 `GET /api/mini/orders/:order_no` 查单（若项目已对接）。

---

## 三、管理后台（Vue）

### 3.1 页面与权限

- **路由：** `/member-vip-settings`
- **菜单：** 杂项 →「会员充值与 VIP」
- **权限：** 与「全局配置」相同，需具备 `admin.config.manage`（见 `Layout.vue`）

### 3.2 接口

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/admin/member-vip-settings` | 读取当前配置 |
| PUT | `/api/admin/member-vip-settings` | 保存配置 |

请求头：与其它后台接口一致，携带 `Authorization: Bearer <token>`。

**GET 响应 `data` 示例：**

```json
{
  "packages_yuan": [100, 200, 500],
  "vip_upgrade_threshold_yuan": 500
}
```

**PUT 请求体示例：**

```json
{
  "packages_yuan": [100, 200, 500],
  "vip_upgrade_threshold_yuan": 500
}
```

- `packages_yuan`：数组，元素为数字（元），服务端会去重、排序并写入 `system_config`。
- `vip_upgrade_threshold_yuan`：填 `0` 表示仅充值、不自动升 VIP。

前端封装：`admin/src/api/memberVip.js`（`getMemberVipSettings`、`updateMemberVipSettings`）。

### 3.3 表单实现提示

`el-form-item` 默认内容区为**横向 flex**，多个块级子节点会横排。本页已用单层 `.field-stack`（纵向 flex + `width: 100%`）包住表单项内容，避免「要填很多项才换行」的布局问题；若二次改造请保留**单根包裹**或自行覆盖 `.el-form-item__content` 对齐方式。

---

## 四、后端配置键（联调参考）

写入 `system_config`，一般无需前端直接调用 `configs` 接口（已由 `member-vip-settings` 聚合）：

| config_key | 含义 |
|------------|------|
| `balance_recharge_packages` | JSON 数组，单位元，如 `[100,200,500]` |
| `vip_upgrade_recharge_threshold_yuan` | 字符串数字，单位元；`0` 关闭满额升 VIP |

---

## 五、相关文件索引

| 角色 | 路径 |
|------|------|
| 小程序接口 | `app/controller/Api/Mini/BalanceRechargeController.php` |
| 支付下单 / 回调 | `app/controller/Api/Mini/PayController.php` |
| 业务逻辑 | `app/service/MemberBalanceRechargeService.php` |
| 后台接口 | `app/controller/Api/Admin/MemberVipConfigController.php` |
| 路由 | `route/app.php` |
| 后台页面 | `admin/src/views/MemberVipSettings.vue` |
