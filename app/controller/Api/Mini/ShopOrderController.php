<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingOrder;
use app\model\FishingVenue;
use app\model\MiniUser;
use app\model\Product;
use app\model\ProductSku;
use app\model\VenueProduct;
use app\model\VenueProductSku;
use app\model\VenueShopOrder;
use app\model\VenueShopOrderItem;
use think\facade\Db;
use think\response\Json;

/**
 * 小程序 - 钓场店铺下单（扣库存、可选会员余额、需微信支付时写 fishing_order）
 *
 * POST /api/mini/venues/:venue_id/shop/orders
 */
class ShopOrderController extends MiniBaseController
{
    private const MAX_LINES = 30;

    private const MAX_QTY = 99;

    public function create(int $venue_id): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }
        /** @var MiniUser $user */

        $venueId = $venue_id;
        $venue = FishingVenue::find($venueId);
        if (!$venue || (int) ($venue->status ?? 0) !== 1) {
            return json(['code' => 404, 'msg' => '钓场不存在或已下架', 'data' => null]);
        }

        $payload = $this->parseJsonBody();
        $items = $payload['items'] ?? [];
        if (!is_array($items) || $items === []) {
            return json(['code' => 400, 'msg' => '请提交商品明细 items', 'data' => null]);
        }
        if (count($items) > self::MAX_LINES) {
            return json(['code' => 400, 'msg' => '单笔订单商品行数过多', 'data' => null]);
        }

        $useBalance = $payload['use_balance'] ?? null;
        if ($useBalance === null || $useBalance === '') {
            $useBalance = true;
        } else {
            $useBalance = (bool) $useBalance;
        }
        $remark = isset($payload['remark']) ? trim((string) $payload['remark']) : '';
        if (mb_strlen($remark) > 200) {
            $remark = mb_substr($remark, 0, 200);
        }

        $lines = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $vpsId = (int) ($row['venue_product_sku_id'] ?? $row['vps_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? $row['qty'] ?? 0);
            if ($vpsId < 1 || $qty < 1) {
                return json(['code' => 400, 'msg' => '无效的商品行', 'data' => null]);
            }
            if ($qty > self::MAX_QTY) {
                return json(['code' => 400, 'msg' => '单 SKU 数量超限', 'data' => null]);
            }
            $key = (string) $vpsId;
            if (!isset($lines[$key])) {
                $lines[$key] = ['vps_id' => $vpsId, 'qty' => 0];
            }
            $lines[$key]['qty'] += $qty;
        }
        if ($lines === []) {
            return json(['code' => 400, 'msg' => '无效的商品明细', 'data' => null]);
        }

        try {
            $result = Db::transaction(function () use ($venueId, $user, $lines, $useBalance, $remark) {
                $miniUserId = (int) $user->id;
                /** @var MiniUser|null $lockedUser */
                $lockedUser = MiniUser::where('id', $miniUserId)->lock(true)->find();
                if (!$lockedUser) {
                    throw new \RuntimeException('用户不存在');
                }

                $prepared = [];
                $goodsTotalFen = 0;

                foreach ($lines as $line) {
                    $vpsId = $line['vps_id'];
                    $qty = $line['qty'];
                    /** @var VenueProductSku|null $vps */
                    $vps = VenueProductSku::where('id', $vpsId)->lock(true)->find();
                    if (!$vps) {
                        throw new \RuntimeException('规格不存在');
                    }
                    if ((int) ($vps->status ?? 0) !== 1) {
                        throw new \RuntimeException('规格已停售');
                    }
                    // ThinkPHP 无 Laravel 的 load()，用关联懒加载或显式查询
                    $vp = $vps->venueProduct;
                    if (!$vp) {
                        $vp = VenueProduct::find((int) $vps->venue_product_id);
                    }
                    if (!$vp || (int) ($vp->venue_id ?? 0) !== $venueId || (int) ($vp->status ?? 0) !== 1) {
                        throw new \RuntimeException('商品不属于该钓场或已下架');
                    }
                    $ps = $vps->productSku;
                    if (!$ps) {
                        $ps = ProductSku::find((int) $vps->product_sku_id);
                    }
                    /** @var Product|null $p */
                    $p = $vp->product ?? Product::find((int) $vp->product_id);
                    if (!$p || (int) ($p->status ?? 0) !== 1) {
                        throw new \RuntimeException('商品不可用');
                    }
                    if (!$ps || (int) ($ps->status ?? 0) !== 1) {
                        throw new \RuntimeException('规格不可用');
                    }
                    if ((int) $ps->product_id !== (int) $vp->product_id) {
                        throw new \RuntimeException('规格与商品不匹配');
                    }
                    $stock = (int) ($vps->stock ?? 0);
                    if ($stock < $qty) {
                        throw new \RuntimeException('库存不足：' . ($p->name ?? '') . ' ' . ($ps->spec_label ?? ''));
                    }
                    $priceYuan = round((float) ($vps->price ?? 0), 2);
                    $priceFen = (int) round($priceYuan * 100);
                    if ($priceFen < 0) {
                        throw new \RuntimeException('价格异常');
                    }
                    $lineTotal = $priceFen * $qty;
                    $goodsTotalFen += $lineTotal;

                    $prepared[] = [
                        'vps' => $vps,
                        'vp' => $vp,
                        'p' => $p,
                        'ps' => $ps,
                        'qty' => $qty,
                        'price_fen' => $priceFen,
                        'line_total_fen' => $lineTotal,
                    ];
                }

                if ($goodsTotalFen < 1) {
                    throw new \RuntimeException('订单金额不能为 0');
                }

                $balanceDeductFen = 0;
                $needWxFen = $goodsTotalFen;
                if ($goodsTotalFen > 0 && $useBalance && (int) ($lockedUser->is_vip ?? 0) === 1) {
                    $userBalanceFen = (int) round(((float) ($lockedUser->balance ?? 0)) * 100);
                    if ($userBalanceFen > 0) {
                        $balanceDeductFen = min($userBalanceFen, $goodsTotalFen);
                        $needWxFen = $goodsTotalFen - $balanceDeductFen;
                        $lockedUser->balance = max(0, ((float) $lockedUser->balance) - $balanceDeductFen / 100);
                        $lockedUser->save();
                    }
                }

                $payChannel = 'wx_mini';
                if ($needWxFen <= 0) {
                    $payChannel = 'balance';
                } elseif ($balanceDeductFen > 0) {
                    $payChannel = 'mixed';
                }

                $orderNo = 'SO' . date('YmdHis') . sprintf('%04d', $miniUserId % 10000) . random_int(1000, 9999);

                /** @var VenueShopOrder $order */
                $order = VenueShopOrder::create([
                    'order_no' => $orderNo,
                    'venue_id' => $venueId,
                    'mini_user_id' => $miniUserId,
                    'amount_goods_fen' => $goodsTotalFen,
                    'balance_deduct_fen' => $balanceDeductFen,
                    'wx_amount_fen' => $needWxFen,
                    'status' => $needWxFen > 0 ? 'pending' : 'paid',
                    'pay_channel' => $payChannel,
                    'remark' => $remark !== '' ? $remark : null,
                    'pay_time' => $needWxFen > 0 ? null : date('Y-m-d H:i:s'),
                    'pay_trade_no' => null,
                ]);

                foreach ($prepared as $row) {
                    $vps = $row['vps'];
                    $vp = $row['vp'];
                    $p = $row['p'];
                    $ps = $row['ps'];
                    VenueShopOrderItem::create([
                        'shop_order_id' => (int) $order->id,
                        'venue_product_id' => (int) $vp->id,
                        'venue_product_sku_id' => (int) $vps->id,
                        'product_id' => (int) $p->id,
                        'product_sku_id' => (int) $ps->id,
                        'product_name' => (string) ($p->name ?? ''),
                        'spec_label' => (string) ($ps->spec_label ?? ''),
                        'price_fen' => $row['price_fen'],
                        'quantity' => $row['qty'],
                        'line_total_fen' => $row['line_total_fen'],
                    ]);
                    $newStock = (int) ($vps->stock ?? 0) - $row['qty'];
                    $vps->stock = max(0, $newStock);
                    $vps->save();
                }

                if ($needWxFen > 0) {
                    FishingOrder::create([
                        'order_no' => $orderNo,
                        'mini_user_id' => $miniUserId,
                        'venue_id' => $venueId,
                        'pond_id' => null,
                        'seat_id' => null,
                        'seat_no' => null,
                        'seat_code' => null,
                        'fee_rule_id' => null,
                        'return_rule_id' => null,
                        'description' => '店铺订单',
                        'amount_total' => $needWxFen,
                        'amount_paid' => 0,
                        'status' => 'pending',
                        'pay_channel' => 'wx_mini',
                    ]);
                }

                return [
                    'order' => $order,
                    'goods_total_fen' => $goodsTotalFen,
                    'balance_deduct_fen' => $balanceDeductFen,
                    'need_wx_fen' => $needWxFen,
                ];
            });
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $code = str_contains($msg, '库存') ? 409 : 400;

            return json(['code' => $code, 'msg' => $msg !== '' ? $msg : '下单失败', 'data' => null]);
        }

        /** @var VenueShopOrder $order */
        $order = $result['order'];
        $needWxFen = (int) $result['need_wx_fen'];

        $amountStr = number_format(round($needWxFen / 100, 2), 2, '.', '');
        $miniPayPath = $needWxFen > 0
            ? '/pages/pay/index?order_no=' . rawurlencode($order->order_no) . '&amount=' . $amountStr
            : null;

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'order_no' => $order->order_no,
                'order_id' => (int) $order->id,
                'venue_id' => $venueId,
                'status' => (string) $order->status,
                'pay_channel' => (string) $order->pay_channel,
                'goods_amount_yuan' => round($result['goods_total_fen'] / 100, 2),
                'balance_deduct_yuan' => round($result['balance_deduct_fen'] / 100, 2),
                'need_pay_yuan' => round($needWxFen / 100, 2),
                'need_pay_fen' => $needWxFen,
                'mini_pay_path' => $miniPayPath,
                'pay_tip' => $needWxFen > 0
                    ? '使用 POST /api/mini/pay/wechat/jsapi，传 order_no 与 description=店铺订单（金额以服务端订单为准）'
                    : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(): array
    {
        $content = (string) $this->request->getContent();
        if ($content !== '') {
            $j = json_decode($content, true);
            if (is_array($j)) {
                return $j;
            }
        }

        $items = $this->request->post('items');

        return [
            'items' => is_array($items) ? $items : [],
            'use_balance' => $this->request->post('use_balance'),
            'remark' => $this->request->post('remark'),
        ];
    }
}
