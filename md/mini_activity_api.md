# 小程序端 — 活动与报名接口

基础路径：`/api`（与现有小程序接口一致，需配置合法域名）

## 1. 活动列表（无需登录）

`GET /api/mini/activities`

| Query | 说明 |
|--------|------|
| `venue_id` | 可选，只展示该钓场下池塘的活动 |
| `pond_id` | 可选，只展示该池塘的活动 |

仅返回 `status=published` 的活动。每条含扩展字段：`pond_name`、`venue_id`、`venue_name`、`can_signup`、`paid_count`、`quota_full`，以及后台配置的 **`allow_balance_deduct`**（`1` 表示允许报名使用会员余额抵扣及免押）。

---

## 2. 活动详情（无需登录）

`GET /api/mini/activities/:id`

返回活动字段 + **`fee_rules`（收费档位 / 活动收费规则，与后台配置一致）** + `pond_name` / `venue_*` + `can_signup` / `paid_count` / `quota_full`。

报名支付时传所选档位的 `fee_rules[].id` 作为 `fee_rule_id`。

---

## 2.1 仅拉收费档位（无需登录）

`GET /api/mini/activities/:id/fee-rules`

仅返回已发布活动的收费规则列表，不拉活动其它字段；适合列表页点进「选档位」时单独刷新。

```json
{
  "code": 0,
  "data": {
    "fee_rules": [ { "id": 1, "name": "正钓4小时", "amount": 200, "deposit": 100, ... } ],
    "total": 1
  }
}
```

---

## 3. 可选钓位列表（无需登录，仅自选模式）

`GET /api/mini/activities/:id/available-seats`

仅当活动 `draw_mode=self_pick` 时有效；否则返回业务错误。  
`data.seats` 为 `{ seat_no, seat_code }[]`，为当前仍可选的钓位。

---

## 4. 我的报名状态（需登录）

`GET /api/mini/activities/:id/my`  
Header：`Authorization: Bearer <token>`

- 未报名：`enrolled: false`
- 已报名：`enrolled: true`，含 `participation`、`order`（若有订单）、`can_draw`（统一抽号且可点抽号）、`can_claim_points`（是否满足领积分前置条件，具体仍以领取接口为准）
- 已分配钓位时（随机/自选支付后、或统一抽号 `draw` 成功后）：`data` 根级提供 **`assigned_seat_no`**（座位号）、**`assigned_seat_code`**（钓位编码，有则返回），与 `POST .../draw` 返回语义一致；详情仍以 `participation.assigned_seat_*` 为准。

---

## 5. 发起报名（生成待支付订单，需登录）

`POST /api/mini/activities/:id/participate`  
Content-Type: `application/json`

| 字段 | 必填 | 说明 |
|------|------|------|
| `fee_rule_id` | 是 | 详情里 `fee_rules[].id`，须属于该活动 |
| `desired_seat_no` | 自选模式必填 | 钓位序号 `seat_no` |
| `use_balance` | 否 | 默认 `true`。仅当活动 **`allow_balance_deduct=1`**（后台开启）时生效：与开钓单一致，**仅会员（`is_vip=1`）** 且为 `true` 时免押金、余额先扣、剩余微信。若活动关闭余额报名，服务端**忽略** `use_balance`，按全额微信（含押金）处理。 |

成功返回示例：

```json
{
  "code": 0,
  "data": {
    "order_no": "A2026...",
    "amount_total_yuan": 128.0,
    "need_pay_yuan": 28.0,
    "balance_deduct_yuan": 100.0,
    "balance_deduct": "100.00",
    "need_pay": "28.00",
    "mini_pay_path": "/pages/pay/index?order_no=A2026...&amount=28.00",
    "participation_id": 1,
    "description": "活动报名预付款",
    "allow_balance_deduct": true,
    "use_balance_requested": true,
    "use_balance_applied": true
  }
}
```

- `allow_balance_deduct`：该活动是否允许余额报名（与列表/详情中字段一致）。  
- `use_balance_requested`：本次请求传入的 `use_balance`（解析后）。  
- `use_balance_applied`：实际是否按余额逻辑处理（活动允许且请求为 true 时为 `true`）。  
- `need_pay_yuan` 为 0 时表示已用余额付清（订单 `pay_channel=balance`），服务端已执行支付成功后的占座/抽号逻辑，**无需**再调 jsapi。  
- `mini_pay_path` 仅在 `need_pay_yuan > 0` 时有值。

---

## 6. 拉起微信支付（需登录）

`POST /api/mini/pay/wechat/jsapi`

与开钓单支付相同，建议传：

- `order_no`：上一步返回的订单号  
- `description`：必须与订单一致，为 **`活动报名预付款`**（服务端已写入 `fishing_order`）  
- 可不传 `total_fee`，服务端以订单金额为准  

支付成功后，微信回调会更新订单；随机/自选模式会自动占座并创建开钓单（逻辑以服务端为准）。  
若报名时使用了会员余额抵扣，开钓单上的 `amount_paid` 为 **微信实付 + 余额抵扣**（与开钓单 `use_balance` 一致）。

---

## 7. 统一抽号（需登录）

`POST /api/mini/activities/:id/draw`  

管理员开启统一抽号后，用户调用此接口分配钓位。

---

## 8. 领取积分（需登录）

`POST /api/mini/activities/:id/points/claim`

活动已开始、开钓单进行中且后台配置了「1元积分」> 0 等条件满足时可领取。  
实付金额 = 订单微信实付 + 报名时 `balance_deduct_fen`（与积分、开钓单展示一致）。

---

## 附：已有库增量字段

- 活动表增加「允许余额报名」开关：[`activity_allow_balance_deduct.sql`](activity_allow_balance_deduct.sql)  
- 报名记录余额抵扣字段：[`activity_participation_balance_fields.sql`](activity_participation_balance_fields.sql)  
- 「1元积分」列默认值改为 0（新建活动默认不发放积分）：[`activity_points_divisor_default_zero.sql`](activity_points_divisor_default_zero.sql)  

新建库可直接使用已更新的 [`activity_module.sql`](activity_module.sql)。
