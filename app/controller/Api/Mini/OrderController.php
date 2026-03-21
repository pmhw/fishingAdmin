<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingOrder;
use app\model\MiniUser;
use app\model\VenueShopOrder;
use think\response\Json;

/**
 * 小程序端 - 订单查询（需登录）
 *
 * GET /api/mini/orders/:order_no
 * Header: Authorization: Bearer {token}
 *
 * 支持：开钓单 fishing_order、店铺订单 venue_shop_order（SO 开头；纯余额支付时可能仅有店铺表）
 */
class OrderController extends MiniBaseController
{
    public function show(string $orderNo): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $orderNo = trim($orderNo);
        if ($orderNo === '') {
            return json(['code' => 400, 'msg' => '订单号无效', 'data' => null]);
        }

        /** @var FishingOrder|null $order */
        $order = FishingOrder::where('order_no', $orderNo)->find();
        if ($order) {
            return $this->jsonFishingOrder($order, $user);
        }

        if (str_starts_with($orderNo, 'SO')) {
            /** @var VenueShopOrder|null $shop */
            $shop = VenueShopOrder::with(['items'])->where('order_no', $orderNo)->find();
            if ($shop) {
                return $this->jsonShopOrderOnly($shop, $user);
            }
        }

        return json(['code' => 404, 'msg' => '订单不存在', 'data' => null]);
    }

    private function jsonFishingOrder(FishingOrder $order, MiniUser $user): Json
    {
        $arr = $order->toArray();
        $amountTotal = (int) ($arr['amount_total'] ?? 0);
        $amountPaid = (int) ($arr['amount_paid'] ?? 0);

        $arr['amount_total_yuan'] = round($amountTotal / 100, 2);
        $arr['amount_paid_yuan'] = round($amountPaid / 100, 2);
        $needPayFen = max(0, $amountTotal - $amountPaid);
        $arr['need_pay_yuan'] = round($needPayFen / 100, 2);
        $arr['amount'] = $arr['need_pay_yuan'];

        $orderUser = $order->user;
        $orderOpenid = $orderUser ? (string) $orderUser->openid : null;
        $arr['mini_user_openid'] = $orderOpenid;

        $currentOpenid = (string) ($user->openid ?? '');
        $arr['openid_match'] = ($orderOpenid !== null && $orderOpenid !== '' && $orderOpenid === $currentOpenid);

        $status = (string) ($arr['status'] ?? '');
        $isPaid = ($status === 'paid') || ($amountTotal > 0 && $amountPaid >= $amountTotal);
        $arr['is_paid'] = $isPaid;
        $arr['pay_status'] = $status;
        $arr['status_text'] = $status === 'paid'
            ? '已支付'
            : ($status === 'pending' ? '待支付' : ($status !== '' ? $status : '未知'));

        $desc = (string) ($arr['description'] ?? '');
        $on = (string) ($arr['order_no'] ?? '');
        if (str_starts_with($on, 'SO') || str_contains($desc, '店铺订单')) {
            $arr['order_kind'] = 'shop';
            $shop = VenueShopOrder::with(['items'])->where('order_no', $on)->find();
            if ($shop) {
                $arr['shop'] = $this->shopSnapshot($shop);
            }
        } else {
            $arr['order_kind'] = 'fishing';
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => $arr,
        ]);
    }

    private function jsonShopOrderOnly(VenueShopOrder $shop, MiniUser $user): Json
    {
        $wxFen = (int) ($shop->wx_amount_fen ?? 0);
        $status = (string) ($shop->status ?? '');
        $wxPaidFen = ($status === 'paid') ? $wxFen : 0;

        $orderUser = $shop->user;
        $orderOpenid = $orderUser ? (string) $orderUser->openid : null;
        $currentOpenid = (string) ($user->openid ?? '');

        $isPaid = $status === 'paid';
        $needPayFen = $isPaid ? 0 : $wxFen;

        $data = [
            'order_no' => (string) $shop->order_no,
            'order_kind' => 'shop',
            'mini_user_id' => (int) $shop->mini_user_id,
            'venue_id' => (int) $shop->venue_id,
            'description' => '店铺订单',
            'amount_total' => $wxFen,
            'amount_paid' => $wxPaidFen,
            'currency' => 'CNY',
            'status' => $status,
            'pay_channel' => (string) ($shop->pay_channel ?? ''),
            'amount_total_yuan' => round($wxFen / 100, 2),
            'amount_paid_yuan' => round($wxPaidFen / 100, 2),
            'need_pay_yuan' => round($needPayFen / 100, 2),
            'amount' => round($needPayFen / 100, 2),
            'goods_amount_yuan' => round((int) ($shop->amount_goods_fen ?? 0) / 100, 2),
            'balance_deduct_yuan' => round((int) ($shop->balance_deduct_fen ?? 0) / 100, 2),
            'mini_user_openid' => $orderOpenid,
            'openid_match' => ($orderOpenid !== null && $orderOpenid !== '' && $orderOpenid === $currentOpenid),
            'is_paid' => $isPaid,
            'pay_status' => $status,
            'status_text' => $status === 'paid' ? '已支付' : ($status === 'pending' ? '待支付' : $status),
            'fishing_session_id' => $shop->fishing_session_id !== null ? (int) $shop->fishing_session_id : null,
            'pond_id' => $shop->pond_id !== null ? (int) $shop->pond_id : null,
            'seat_no' => $shop->seat_no !== null ? (int) $shop->seat_no : null,
            'seat_code' => (string) ($shop->seat_code ?? '') !== '' ? (string) $shop->seat_code : null,
            'shop' => $this->shopSnapshot($shop),
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shopSnapshot(VenueShopOrder $shop): array
    {
        $items = [];
        foreach ($shop->items ?? [] as $it) {
            $items[] = [
                'product_name' => (string) ($it->product_name ?? ''),
                'spec_label' => (string) ($it->spec_label ?? ''),
                'price_yuan' => round((int) ($it->price_fen ?? 0) / 100, 2),
                'quantity' => (int) ($it->quantity ?? 0),
                'line_total_yuan' => round((int) ($it->line_total_fen ?? 0) / 100, 2),
            ];
        }

        $seatCode = (string) ($shop->seat_code ?? '');
        $seatNo = $shop->seat_no !== null ? (int) $shop->seat_no : null;
        $seatLabel = $seatCode !== '' ? $seatCode : ($seatNo !== null && $seatNo > 0 ? '#' . $seatNo : '—');

        return [
            'order_id' => (int) $shop->id,
            'fishing_session_id' => $shop->fishing_session_id !== null ? (int) $shop->fishing_session_id : null,
            'pond_id' => $shop->pond_id !== null ? (int) $shop->pond_id : null,
            'seat_id' => $shop->seat_id !== null ? (int) $shop->seat_id : null,
            'seat_no' => $seatNo,
            'seat_code' => $seatCode !== '' ? $seatCode : null,
            'seat_label' => $seatLabel,
            'amount_goods_yuan' => round((int) ($shop->amount_goods_fen ?? 0) / 100, 2),
            'balance_deduct_yuan' => round((int) ($shop->balance_deduct_fen ?? 0) / 100, 2),
            'wx_amount_yuan' => round((int) ($shop->wx_amount_fen ?? 0) / 100, 2),
            'items' => $items,
        ];
    }
}
