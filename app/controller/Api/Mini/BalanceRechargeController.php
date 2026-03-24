<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\service\MemberBalanceRechargeService;
use think\response\Json;

/**
 * 会员余额充值：档位与下单（支付走 POST /api/mini/pay/wechat/jsapi）
 */
class BalanceRechargeController extends MiniBaseController
{
    /**
     * GET /api/mini/user/recharge/options
     * 无需登录（仅展示档位与规则）
     */
    public function options(): Json
    {
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => MemberBalanceRechargeService::getOptionsForMini(),
        ]);
    }

    /**
     * POST /api/mini/user/recharge/order
     * body: { "amount_yuan": 100 }
     * 返回 order_no、total_fee（分）、description，供 jsapi 使用
     */
    public function createOrder(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $amountYuan = $this->request->post('amount_yuan', null);
        if ($amountYuan === null || $amountYuan === '') {
            return json(['code' => 400, 'msg' => '请传入 amount_yuan', 'data' => null]);
        }
        if (!is_numeric($amountYuan)) {
            return json(['code' => 400, 'msg' => 'amount_yuan 格式错误', 'data' => null]);
        }
        $yuan = (float) $amountYuan;

        try {
            $order = MemberBalanceRechargeService::createOrder($user, $yuan);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage(), 'data' => null]);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'order_no'    => (string) $order->order_no,
                'total_fee'   => (int) $order->amount_total,
                'description' => (string) $order->description,
            ],
        ]);
    }
}
