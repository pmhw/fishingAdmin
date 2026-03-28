# 小程序 · 交易聚合列表（无新表）

从 **`fishing_order`**、**`venue_shop_order`**、**`pond_return_log`**（关联当前用户的 `fishing_session`）合并为一条时间线，**不新增数据库表**。

---

## 接口

| 方法 | 路径 | 鉴权 |
|------|------|------|
| GET | `/api/mini/trades` | 需登录 `Authorization: Bearer {token}` |

无 `/api` 前缀的部署若注册了兼容路由，亦可使用同路径 `mini/trades`（见 `route/app.php`）。

### Query 参数

| 参数 | 说明 |
|------|------|
| `page` | 页码，默认 `1` |
| `limit` | 每页条数，默认 `10`，最大 `50` |
| `kind` | `all`（默认）\|`fishing`\|`shop`\|`return`，只拉某一类 |

### 响应 `data`

| 字段 | 说明 |
|------|------|
| `list` | 当前页记录（按 `sort_ts` 倒序） |
| `total` | 总条数（筛选后） |
| `page` / `limit` | 分页参数回显 |

### `list[]` 公共字段

| 字段 | 说明 |
|------|------|
| `item_type` | `fishing_order` \| `shop_order` \| `return_log` |
| `ref_id` | 各表主键 |
| `biz_no` | 展示用业务号；店铺/钓场订单多为真实 `order_no`；回鱼为 `RL{id}` |
| `title` / `subtitle` | 列表主副标题 |
| `status` / `status_text` | 状态码与文案 |
| `amount_yuan` | 金额字符串（两位小数） |
| `sort_ts` | 排序时间（钓场/店铺优先 `pay_time`，否则 `created_at`；回鱼为记录时间） |

### 按类型的额外字段

**`fishing_order`**

- `order_no`：同 `biz_no`，可调用 `GET /api/mini/orders/:order_no` 拉详情（与支付页一致）。
- `paid_yuan`、`need_pay_yuan`：已付/待付（分转元）。

**`shop_order`**

- `order_no`：同 `biz_no`（`SO` 开头），详情同上。
- `balance_deduct_yuan`、`wx_pay_yuan`：余额抵扣与微信应付（元）。

**`return_log`**

- `payout_channel`：若库表已加打款字段，可能为 `balance` / `wechat`。
- 用户确认收款：`GET /api/mini/return-payouts/:id/package`（`id` 为 `ref_id`）。

---

## 去重说明

- **`SO` 开头**或描述含「店铺订单」的 **`fishing_order`** 不在本列表出现，**以 `venue_shop_order` 为准**，避免店铺单双条。
- 纯余额支付的店铺单可能**仅有** `venue_shop_order`，仍会出现在 `kind=shop` / `all` 中。

---

## 后端文件

- `app/controller/Api/Mini/TradeController.php`
- 路由：`route/app.php` → `mini/trades`
