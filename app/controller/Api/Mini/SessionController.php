<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingOrder;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\MiniUser;
use app\model\Activity;
use app\model\PondFeeRule;
use app\model\PondSeat;
use think\response\Json;

/**
 * 小程序端 - 开钓单（开卡）
 *
 * POST /api/mini/sessions
 * body: venue_id, pond_id, seat_id?, fee_rule_id, use_balance?
 * 逻辑与后台开钓单一致：会员免押金、余额抵扣、订单+（可能）开钓单。
 */
class SessionController extends MiniBaseController
{
    /**
     * 小程序用户自主开卡
     */
    public function create(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }
        /** @var MiniUser $user */

        $venueId    = (int) $this->request->post('venue_id', 0);
        $pondId     = (int) $this->request->post('pond_id', 0);
        $seatId     = (int) $this->request->post('seat_id', 0);
        $feeRuleId  = (int) $this->request->post('fee_rule_id', 0);
        $useBalance = $this->request->post('use_balance');
        if ($useBalance === null || $useBalance === '') {
            $useBalance = true;
        } else {
            $useBalance = (bool) $useBalance;
        }

        $miniUserId = (int) $user->id;

        if ($venueId < 1 || $pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择钓场和池塘', 'data' => null]);
        }
        if (!FishingVenue::find($venueId)) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        /** @var FishingPond|null $pond */
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if ((int) $pond->venue_id !== $venueId) {
            return json(['code' => 400, 'msg' => '池塘不属于该钓场', 'data' => null]);
        }

        // 活动期间禁止开钓单（仅禁用小程序端创建）
        $activeActivity = Activity::where('pond_id', $pondId)
            ->where('status', 'published')
            ->find();
        if ($activeActivity) {
            return json(['code' => 403, 'msg' => '该池塘正在进行活动，暂时不能开钓单', 'data' => null]);
        }

        $seatNo   = null;
        $seatCode = null;
        if ($seatId > 0) {
            /** @var PondSeat|null $seat */
            $seat = PondSeat::find($seatId);
            if (!$seat) {
                return json(['code' => 404, 'msg' => '钓位不存在', 'data' => null]);
            }
            if ((int) $seat->pond_id !== $pondId) {
                return json(['code' => 400, 'msg' => '钓位不属于当前池塘', 'data' => null]);
            }
            $seatNo   = (int) $seat->seat_no;
            $seatCode = (string) $seat->code;

            $exists = FishingSession::where('seat_id', $seatId)->where('status', 'ongoing')->find();
            if ($exists) {
                return json(['code' => 400, 'msg' => '该钓位当前已有开钓单，请先结束或选择其他钓位', 'data' => null]);
            }
        }

        if ($feeRuleId < 1) {
            return json(['code' => 400, 'msg' => '请选择收费规则', 'data' => null]);
        }
        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return json(['code' => 404, 'msg' => '收费规则不存在', 'data' => null]);
        }
        if ((int) $fee->pond_id !== $pondId) {
            return json(['code' => 400, 'msg' => '收费规则不属于当前池塘', 'data' => null]);
        }

        $amountFen     = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositRawFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $depositTotalFen = $depositRawFen;
        if ((int) ($user->is_vip ?? 0) === 1 && $useBalance && $depositRawFen > 0) {
            $depositTotalFen = 0;
        }
        $amountTotalFen = max(0, $amountFen + $depositTotalFen);

        $expireTime = null;
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                $expireTime = date('Y-m-d H:i:s', time() + $seconds);
            }
        }

        $balanceDeductFen = 0;
        $needPayFen = $amountTotalFen;
        if ($amountTotalFen > 0 && $useBalance && (int) ($user->is_vip ?? 0) === 1) {
            $userBalanceFen = (int) round(((float) ($user->balance ?? 0)) * 100);
            if ($userBalanceFen > 0) {
                $balanceDeductFen = min($userBalanceFen, $amountTotalFen);
                $needPayFen = $amountTotalFen - $balanceDeductFen;
                $user->balance = max(0, ((float) $user->balance) - $balanceDeductFen / 100);
                $user->save();
            }
        }

        $orderNo = 'O' . date('YmdHis') . mt_rand(1000, 9999);
        $session = null;

        if ($needPayFen > 0) {
            $order = FishingOrder::create([
                'order_no'     => $orderNo,
                'mini_user_id' => $miniUserId,
                'venue_id'     => $venueId,
                'pond_id'      => $pondId,
                'seat_id'      => $seatId ?: null,
                'seat_no'      => $seatNo,
                'seat_code'    => $seatCode,
                'fee_rule_id'  => $feeRuleId,
                'description'  => '开钓单预付款',
                'amount_total' => $needPayFen,
                'amount_paid'  => 0,
                'status'       => 'pending',
                'pay_channel'  => 'wx_mini',
            ]);
        } else {
            $order = FishingOrder::create([
                'order_no'     => $orderNo,
                'mini_user_id' => $miniUserId,
                'venue_id'     => $venueId,
                'pond_id'      => $pondId,
                'seat_id'      => $seatId ?: null,
                'seat_no'      => $seatNo,
                'seat_code'    => $seatCode,
                'fee_rule_id'  => $feeRuleId,
                'description'  => '开钓单余额支付',
                'amount_total' => $amountTotalFen,
                'amount_paid'  => $amountTotalFen,
                'status'       => 'paid',
                'pay_channel'  => 'balance',
                'pay_time'     => date('Y-m-d H:i:s'),
            ]);

            $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);
            $session = FishingSession::create([
                'session_no'    => $sessionNo,
                'mini_user_id'  => $miniUserId,
                'venue_id'      => $venueId,
                'pond_id'       => $pondId,
                'seat_id'       => $seatId ?: null,
                'seat_no'       => $seatNo,
                'seat_code'     => $seatCode,
                'fee_rule_id'   => $feeRuleId,
                'order_id'      => $order->id,
                'start_time'    => date('Y-m-d H:i:s'),
                'expire_time'   => $expireTime,
                'status'        => 'ongoing',
                'amount_total'  => $amountTotalFen,
                'amount_paid'   => $amountTotalFen,
                'deposit_total' => $depositTotalFen,
                'remark'        => '余额支付自动开钓单',
            ]);
        }

        $amountYuanNeed = round($needPayFen / 100, 2);
        $amountStr = number_format($amountYuanNeed, 2, '.', '');
        $miniPayPath = $needPayFen > 0
            ? '/pages/pay/index?order_no=' . $orderNo . '&amount=' . $amountStr
            : null;

        $resp = [
            'session'        => $session ? $session->toArray() : null,
            'order'          => [
                'order_no' => $orderNo,
                'id'       => $order->id,
            ],
            'balance_deduct' => number_format($balanceDeductFen / 100, 2, '.', ''),
            'need_pay'       => number_format($needPayFen / 100, 2, '.', ''),
            'mini_pay_path'  => $miniPayPath,
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $resp]);
    }
}
