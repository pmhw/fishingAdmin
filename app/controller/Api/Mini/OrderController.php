<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\FishingOrder;
use think\response\Json;

/**
 * 小程序端 - 订单查询（需登录）
 *
 * GET /api/mini/orders/:order_no
 * Header: Authorization: Bearer {token}
 *
 * 用途：
 * - 支付页在仅拿到 order_no（例如通过 scene）时，根据当前用户 openid 校验并获取订单金额等信息
 */
class OrderController extends MiniBaseController
{
    /**
     * 根据订单号获取当前登录用户的订单信息
     * GET /api/mini/orders/:order_no
     */
    public function show(string $orderNo): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        /** @var FishingOrder|null $order */
        $order = FishingOrder::where('order_no', $orderNo)->find();
        if (!$order) {
            return json(['code' => 404, 'msg' => '订单不存在', 'data' => null]);
        }

        // 所有权校验：仅允许创建该订单的用户查询
        if ((int) $order->mini_user_id !== (int) $user->id) {
            return json(['code' => 403, 'msg' => '无权查看该订单', 'data' => null]);
        }

        $arr = $order->toArray();
        $amountTotal = (int) ($arr['amount_total'] ?? 0);
        $amountPaid  = (int) ($arr['amount_paid'] ?? 0);

        // 元字段（方便前端显示和兼容）
        $arr['amount_total_yuan'] = round($amountTotal / 100, 2);
        $arr['amount_paid_yuan']  = round($amountPaid / 100, 2);
        $needPayFen = max(0, $amountTotal - $amountPaid);
        $arr['need_pay_yuan']     = round($needPayFen / 100, 2);

        // 兼容字段名：amount（用于支付页展示）
        $arr['amount'] = $arr['need_pay_yuan'];

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $arr,
        ]);
    }
}

