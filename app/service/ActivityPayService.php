<?php
declare(strict_types = 1);

namespace app\service;

use app\model\Activity;
use app\model\ActivityParticipation;
use app\model\FishingOrder;
use app\model\FishingSession;
use app\model\PondFeeRule;
use app\model\PondSeat;

/**
 * 活动报名支付成功后的业务：更新参与记录、分配钓位、创建 fishing_session（与 PayController 回调共用）
 */
class ActivityPayService
{
    /**
     * 活动开钓单到期时间：活动开钓时间 + 收费规则时长；规则未配置时长时默认 +24 小时，
     * 保证 SessionExpireService 能按 expire_time 自动截单（避免 expire 为空永远不结束）。
     * 始终返回合法 datetime 字符串（开钓时间异常时退化为「当前 +24h」，避免写入 NULL）。
     */
    public static function computeActivitySessionExpireAt(Activity $activity, PondFeeRule $fee): string
    {
        $openTs = strtotime((string) ($activity->open_time ?? ''));
        if ($openTs <= 0) {
            return date('Y-m-d H:i:s', time() + 86400);
        }
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                return date('Y-m-d H:i:s', $openTs + $seconds);
            }
        }

        return date('Y-m-d H:i:s', $openTs + 86400);
    }

    /**
     * 开钓单展示用：应收、实收、押金（与会员免押金、余额抵扣一致）
     *
     * @return array{amount_total_fen:int, amount_paid_fen:int, deposit_total_fen:int}
     */
    public static function sessionMoneyForActivity(
        ?FishingOrder $order,
        ActivityParticipation $part,
        PondFeeRule $fee
    ): array {
        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositRawFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $depositEffectiveFen = ((int) ($part->deposit_waived ?? 0) === 1) ? 0 : $depositRawFen;
        $sessionAmountTotalFen = max(0, $amountFen + $depositEffectiveFen);
        $wxPaidFen = $order ? (int) ($order->amount_paid ?? 0) : 0;
        $balanceFen = (int) ($part->balance_deduct_fen ?? 0);
        $sessionAmountPaidFen = $wxPaidFen + $balanceFen;

        return [
            'amount_total_fen'  => $sessionAmountTotalFen,
            'amount_paid_fen'   => $sessionAmountPaidFen,
            'deposit_total_fen' => $depositEffectiveFen,
        ];
    }

    /**
     * 微信支付回调或「纯余额」报名后立即执行
     */
    public static function processAfterPaid(FishingOrder $order): void
    {
        $desc = (string) ($order->description ?? '');
        if ($desc === '' || mb_strpos($desc, '活动报名预付款') === false) {
            return;
        }

        $orderNo = (string) ($order->order_no ?? '');
        $miniUserId = (int) ($order->mini_user_id ?? 0);
        if ($orderNo === '' || $miniUserId < 1) {
            return;
        }

        /** @var ActivityParticipation|null $part */
        $part = ActivityParticipation::where('pay_order_no', $orderNo)
            ->where('mini_user_id', $miniUserId)
            ->find();
        if (!$part) {
            return;
        }

        if ((string) ($part->pay_status ?? '') !== 'paid') {
            $part->pay_status = 'paid';
            $part->save();
        }

        $activityId = (int) ($part->activity_id ?? 0);
        if ($activityId < 1) {
            return;
        }
        /** @var Activity|null $activity */
        $activity = Activity::find($activityId);
        if (!$activity) {
            return;
        }

        $drawMode = (string) ($activity->draw_mode ?? 'random');
        if ($drawMode === 'unified') {
            $part->draw_status = 'draw_waiting_unified';
            $part->save();
            return;
        }
        if ($drawMode === 'offline') {
            $part->draw_status = 'draw_waiting_offline';
            $part->save();
            return;
        }
        if (!empty($part->assigned_session_id) || !empty($part->assigned_seat_id)) {
            return;
        }

        $pondId = (int) ($activity->pond_id ?? 0);
        $feeRuleId = (int) ($part->fee_rule_id ?? 0);
        if ($pondId < 1 || $feeRuleId < 1) {
            return;
        }
        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return;
        }

        $occupiedSeatIds = FishingSession::where('pond_id', $pondId)
            ->where('status', 'ongoing')
            ->where('seat_id', '>', 0)
            ->column('seat_id');
        $occupiedSeatIds = array_flip(array_map('intval', is_array($occupiedSeatIds) ? $occupiedSeatIds : []));

        $assignedSeatIds = ActivityParticipation::where('activity_id', $activityId)
            ->whereNotNull('assigned_seat_id')
            ->where('assigned_seat_id', '>', 0)
            ->column('assigned_seat_id');
        $assignedSeatIds = array_flip(array_map('intval', is_array($assignedSeatIds) ? $assignedSeatIds : []));

        $seatId = 0;
        $seatNo = 0;
        $seatCode = '';
        if ($drawMode === 'self_pick') {
            $desiredSeatNo = (int) ($part->desired_seat_no ?? 0);
            if ($desiredSeatNo < 1) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
            /** @var PondSeat|null $seat */
            $seat = PondSeat::where('pond_id', $pondId)->where('seat_no', $desiredSeatNo)->find();
            if (!$seat) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
            $seatId = (int) ($seat->id ?? 0);
            $seatNo = (int) ($seat->seat_no ?? 0);
            $seatCode = (string) ($seat->code ?? '');
            if ($seatId < 1 || isset($occupiedSeatIds[$seatId]) || isset($assignedSeatIds[$seatId])) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
        } else {
            $seats = PondSeat::where('pond_id', $pondId)->order('seat_no', 'asc')->select();
            $free = [];
            foreach ($seats as $s) {
                $sid = (int) ($s->id ?? 0);
                if ($sid < 1) {
                    continue;
                }
                if (isset($occupiedSeatIds[$sid]) || isset($assignedSeatIds[$sid])) {
                    continue;
                }
                $free[] = $s;
            }
            if (empty($free)) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
            $pick = $free[random_int(0, count($free) - 1)];
            $seatId = (int) ($pick->id ?? 0);
            $seatNo = (int) ($pick->seat_no ?? 0);
            $seatCode = (string) ($pick->code ?? '');
        }

        $money = self::sessionMoneyForActivity($order, $part, $fee);

        $expireTime = self::computeActivitySessionExpireAt($activity, $fee);

        $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);
        $session = FishingSession::create([
            'session_no'    => $sessionNo,
            'mini_user_id'  => $miniUserId,
            'venue_id'      => (int) ($order->venue_id ?? 0),
            'pond_id'       => $pondId,
            'seat_id'       => $seatId,
            'seat_no'       => $seatNo,
            'seat_code'     => $seatCode,
            'fee_rule_id'   => $feeRuleId,
            'order_id'      => (int) ($order->id ?? 0),
            'start_time'    => (string) ($activity->open_time ?? date('Y-m-d H:i:s')),
            'expire_time'   => $expireTime,
            'status'        => 'ongoing',
            'amount_total'  => $money['amount_total_fen'],
            'amount_paid'   => $money['amount_paid_fen'],
            'deposit_total' => $money['deposit_total_fen'],
            'remark'        => '活动支付成功占座',
        ]);

        $part->assigned_seat_id = $seatId;
        $part->assigned_seat_no = $seatNo;
        $part->assigned_session_id = (int) ($session->id ?? 0);
        $part->draw_status = 'assigned';
        $part->save();
    }
}
