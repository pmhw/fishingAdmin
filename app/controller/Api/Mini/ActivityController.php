<?php
declare(strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\Activity;
use app\model\ActivityParticipation;
use app\model\FishingOrder;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\PondFeeRule;
use app\model\PondSeat;
use app\model\MiniUserPointsLedger;
use think\response\Json;

/**
 * 活动模块（小程序侧）
 *
 * 说明（MVP）：
 * - 报名付费：创建 activity_participation + fishing_order（pending），前端再调用 mini/pay/wechat/jsapi 完成支付
 * - 抽号：
 *    * random/self_pick：支付回调 notify 自动分配座位并创建 fishing_session(status=ongoing)
 *    * unified：管理员开启后，用户点击 draw 按钮，服务端逐个分配座位并创建 session
 * - 积分领取：在 activity 开始后、且当前 session 仍未到期时领取
 */
class ActivityController extends MiniBaseController
{
    private const ORDER_DESC = '活动报名预付款';

    /**
     * 活动报名 + 生成支付订单（不直接返回支付参数）
     *
     * POST /api/mini/activities/:id/participate
     * body:
     * - fee_rule_id（pond_fee_rule.id）
     * - desired_seat_no（仅 self_pick 需要）
     */
    public function participate(int $id): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => 'activity_id 不合法', 'data' => null]);
        }

        /** @var Activity|null $activity */
        $activity = Activity::find($activityId);
        if (!$activity) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if ((string) ($activity->status ?? '') !== 'published') {
            return json(['code' => 400, 'msg' => '活动未发布或不可报名', 'data' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $openTime = (string) ($activity->open_time ?? '0000-00-00 00:00:00');
        $deadline = (string) ($activity->register_deadline ?? '0000-00-00 00:00:00');
        if ($now > $deadline) {
            return json(['code' => 400, 'msg' => '报名已截止', 'data' => null]);
        }
        if ($now >= $openTime) {
            return json(['code' => 400, 'msg' => '活动已开始，停止报名', 'data' => null]);
        }

        $feeRuleId = (int) ($this->request->post('fee_rule_id', 0));
        if ($feeRuleId < 1) {
            return json(['code' => 400, 'msg' => '请选择收费规则', 'data' => null]);
        }
        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return json(['code' => 404, 'msg' => '收费规则不存在', 'data' => null]);
        }
        if ((int) ($fee->pond_id ?? 0) !== (int) ($activity->pond_id ?? 0)) {
            return json(['code' => 400, 'msg' => '收费规则不属于活动池塘', 'data' => null]);
        }
        if ((int) ($fee->activity_id ?? 0) !== $activityId) {
            return json(['code' => 400, 'msg' => '收费规则不属于该活动', 'data' => null]);
        }

        $desiredSeatNo = null;
        $drawMode = (string) ($activity->draw_mode ?? 'random');
        if ($drawMode === 'self_pick') {
            $desiredSeatNo = (int) ($this->request->post('desired_seat_no', 0));
            if ($desiredSeatNo < 1) {
                return json(['code' => 400, 'msg' => '请填写要自选的座位号', 'data' => null]);
            }
        }

        // 一人一活动（按 activity_participation unique）
        $exists = ActivityParticipation::where('activity_id', $activityId)
            ->where('mini_user_id', (int) $user->id)
            ->find();
        if ($exists) {
            return json(['code' => 400, 'msg' => '你已报名该活动', 'data' => null]);
        }

        // 名额校验（只统计已支付）
        $limitCount = (int) ($activity->participant_count ?? 0);
        if ($limitCount > 0) {
            $paidCount = ActivityParticipation::where('activity_id', $activityId)
                ->where('pay_status', 'paid')
                ->count();
            if ((int) $paidCount >= $limitCount) {
                return json(['code' => 400, 'msg' => '活动名额已满', 'data' => null]);
            }
        }

        // 计算订单金额（金额 + 押金）
        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $amountTotalFen = max(0, $amountFen + $depositFen);
        if ($amountTotalFen <= 0) {
            return json(['code' => 400, 'msg' => '活动收费金额异常', 'data' => null]);
        }

        // 为订单与参与记录生成唯一 order_no
        $orderNo = 'A' . date('YmdHis') . mt_rand(1000, 9999) . random_int(100, 999);

        $pondId = (int) $activity->pond_id;
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return json(['code' => 404, 'msg' => '活动池塘不存在', 'data' => null]);
        }
        $venueId = (int) ($pond->venue_id ?? 0);

        // 自选座位：校验该 seat_no 存在且未被占用/分配
        $seatId = null;
        $seatNo = null;
        $seatCode = null;
        if ($desiredSeatNo !== null) {
            /** @var PondSeat|null $seat */
            $seat = PondSeat::where('pond_id', $pondId)->where('seat_no', $desiredSeatNo)->find();
            if (!$seat) {
                return json(['code' => 400, 'msg' => '该座位号不存在', 'data' => null]);
            }
            $seatId = (int) $seat->id;
            $seatNo = (int) $seat->seat_no;
            $seatCode = (string) ($seat->code ?? '');

            $occupiedSeatIds = FishingSession::where('pond_id', $pondId)
                ->where('status', 'ongoing')
                ->whereNotNull('seat_id')
                ->where('seat_id', '>', 0)
                ->column('seat_id');
            $occupiedSeatIds = array_flip(array_map('intval', is_array($occupiedSeatIds) ? $occupiedSeatIds : []));

            $assignedSeatIds = ActivityParticipation::where('activity_id', $activityId)
                ->whereNotNull('assigned_seat_id')
                ->where('assigned_seat_id', '>', 0)
                ->column('assigned_seat_id');
            $assignedSeatIds = array_flip(array_map('intval', is_array($assignedSeatIds) ? $assignedSeatIds : []));

            if (isset($occupiedSeatIds[$seatId]) || isset($assignedSeatIds[$seatId])) {
                return json(['code' => 400, 'msg' => '该座位已被占用或已分配，请刷新再选', 'data' => null]);
            }
        }

        // 写入 activity_participation（待支付）
        $participation = ActivityParticipation::create([
            'activity_id'          => $activityId,
            'mini_user_id'         => (int) $user->id,
            'fee_rule_id'          => $feeRuleId,
            'pay_order_no'        => $orderNo,
            'pay_status'          => 'pending',
            'draw_status'         => 'waiting_paid',
            'desired_seat_no'     => $desiredSeatNo,
            'assigned_seat_id'    => null,
            'assigned_seat_no'    => null,
            'assigned_session_id' => null,
        ]);

        // 写入 fishing_order（待支付）
        FishingOrder::create([
            'order_no'      => $orderNo,
            'mini_user_id'  => (int) $user->id,
            'venue_id'      => $venueId > 0 ? $venueId : null,
            'pond_id'       => $pondId > 0 ? $pondId : null,
            'seat_id'       => $seatId ? (int) $seatId : null,
            'seat_no'       => $seatNo ? (int) $seatNo : null,
            'seat_code'     => $seatCode !== '' ? (string) $seatCode : null,
            'fee_rule_id'   => $feeRuleId,
            'return_rule_id'=> null,
            'description'   => self::ORDER_DESC,
            'amount_total'  => $amountTotalFen,
            'amount_paid'   => 0,
            'status'        => 'pending',
            'pay_channel'   => 'wx_mini',
            'raw_notify'    => null,
        ]);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'order_no' => $orderNo,
                'amount_total_yuan' => round($amountTotalFen / 100, 2),
                'participation_id'  => (int) $participation->id,
                'description' => self::ORDER_DESC,
            ],
        ]);
    }

    /**
     * 统一抽号（unified 模式）：用户点击抽号后逐个分配 seat_no 并创建 fishing_session
     *
     * POST /api/mini/activities/:id/draw
     */
    public function draw(int $id): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => 'activity_id 不合法', 'data' => null]);
        }

        /** @var Activity|null $activity */
        $activity = Activity::find($activityId);
        if (!$activity) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if ((string) ($activity->status ?? '') !== 'published') {
            return json(['code' => 400, 'msg' => '活动不可抽号', 'data' => null]);
        }
        if ((string) ($activity->draw_mode ?? '') !== 'unified') {
            return json(['code' => 400, 'msg' => '该活动不是统一抽号模式', 'data' => null]);
        }
        if ((int) ($activity->unified_draw_enabled ?? 0) !== 1) {
            return json(['code' => 400, 'msg' => '管理员尚未开启抽号', 'data' => null]);
        }

        $pondId = (int) ($activity->pond_id ?? 0);
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '活动池塘缺失', 'data' => null]);
        }

        // 找到该用户已支付但未分配座位的参与记录
        $part = ActivityParticipation::where('activity_id', $activityId)
            ->where('mini_user_id', (int) $user->id)
            ->where('pay_status', 'paid')
            ->where(function ($q) {
                $q->where('draw_status', 'draw_waiting_unified')
                  ->orWhere('draw_status', 'waiting_paid');
            })
            ->find();
        if (!$part) {
            return json(['code' => 400, 'msg' => '你没有可抽号的参与记录', 'data' => null]);
        }
        if (!empty($part->assigned_seat_id)) {
            return json(['code' => 400, 'msg' => '你已抽到座位', 'data' => null]);
        }

        // 获取空闲座位集合：排除正在占用的 seat_id、以及其它参与已分配的 seat
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

        $freeSeats = PondSeat::where('pond_id', $pondId)
            ->order('seat_no', 'asc')
            ->select();

        $free = [];
        foreach ($freeSeats as $s) {
            $sid = (int) ($s->id ?? 0);
            if ($sid < 1) continue;
            if (isset($occupiedSeatIds[$sid]) || isset($assignedSeatIds[$sid])) {
                continue;
            }
            $free[] = $s;
        }

        if (empty($free)) {
            return json(['code' => 400, 'msg' => '座位已分配完毕', 'data' => null]);
        }

        $pickIdx = random_int(0, count($free) - 1);
        $seat = $free[$pickIdx];

        $seatId = (int) $seat->id;
        $seatNo = (int) $seat->seat_no;
        $seatCode = (string) ($seat->code ?? '');

        // 创建占座 session（start_time 用 activity.open_time，保证到期逻辑正确）
        $feeRuleId = (int) ($part->fee_rule_id ?? 0);
        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return json(['code' => 400, 'msg' => '收费规则不存在', 'data' => null]);
        }

        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $amountTotalFen = max(0, $amountFen + $depositFen);

        // 计算 expire_time
        $expireTime = null;
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                $expireTime = date('Y-m-d H:i:s', strtotime((string) $activity->open_time) + $seconds);
            }
        }

        $order = FishingOrder::where('order_no', (string) ($part->pay_order_no ?? ''))->find();
        $orderPaidFen = $order ? (int) ($order->amount_paid ?? 0) : $amountTotalFen;

        $pond = FishingPond::find($pondId);
        $venueId = $pond ? (int) ($pond->venue_id ?? 0) : 0;

        $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);
        $session = FishingSession::create([
            'session_no'    => $sessionNo,
            'mini_user_id'  => (int) $user->id,
            'venue_id'      => $venueId,
            'pond_id'       => $pondId,
            'seat_id'       => $seatId,
            'seat_no'       => $seatNo,
            'seat_code'     => $seatCode,
            'fee_rule_id'   => $feeRuleId,
            'order_id'      => $order ? (int) ($order->id ?? 0) : null,
            'start_time'    => (string) $activity->open_time,
            'expire_time'   => $expireTime,
            'status'        => 'ongoing',
            'amount_total'  => $amountTotalFen,
            'amount_paid'   => $orderPaidFen,
            'deposit_total' => $depositFen,
            'remark'        => '活动统一抽号占座',
        ]);

        $part->assigned_seat_id = $seatId;
        $part->assigned_seat_no = $seatNo;
        $part->assigned_session_id = (int) $session->id;
        $part->draw_status = 'assigned';
        $part->save();

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'assigned_seat_no' => $seatNo,
                'assigned_seat_code' => $seatCode,
                'session_id' => (int) $session->id,
            ],
        ]);
    }

    /**
     * 领取活动积分
     * POST /api/mini/activities/:id/points/claim
     */
    public function claimPoints(int $id): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => 'activity_id 不合法', 'data' => null]);
        }

        $activity = Activity::find($activityId);
        if (!$activity) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if ((string) ($activity->status ?? '') !== 'published') {
            return json(['code' => 400, 'msg' => '活动不可领取', 'data' => null]);
        }

        $part = ActivityParticipation::where('activity_id', $activityId)
            ->where('mini_user_id', (int) $user->id)
            ->find();
        if (!$part) {
            return json(['code' => 400, 'msg' => '未找到你的参与记录', 'data' => null]);
        }
        if ((string) ($part->draw_status ?? '') !== 'assigned') {
            return json(['code' => 400, 'msg' => '你还未抽到/分配到座位', 'data' => null]);
        }
        if (!empty($part->claimed_points_at)) {
            return json(['code' => 400, 'msg' => '你已领取过积分', 'data' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $openTime = (string) ($activity->open_time ?? '0000-00-00 00:00:00');
        if ($now < $openTime) {
            return json(['code' => 400, 'msg' => '活动尚未开始', 'data' => null]);
        }

        $session = null;
        if (!empty($part->assigned_session_id)) {
            $session = FishingSession::find((int) $part->assigned_session_id);
        }
        if (!$session || (string) ($session->status ?? '') !== 'ongoing') {
            return json(['code' => 400, 'msg' => '你的开钓单未处于进行中，暂不可领取', 'data' => null]);
        }
        if (!empty($session->expire_time) && (string) $session->expire_time !== '0000-00-00 00:00:00' && $now > (string) $session->expire_time) {
            return json(['code' => 400, 'msg' => '活动时间已结束，暂不可领取', 'data' => null]);
        }

        $orderNo = (string) ($part->pay_order_no ?? '');
        $order = FishingOrder::where('order_no', $orderNo)->find();
        if (!$order || (string) ($order->status ?? '') !== 'paid') {
            return json(['code' => 400, 'msg' => '支付未完成，暂不可领取', 'data' => null]);
        }

        $divisor = max(1, (int) ($activity->points_divisor ?? 1));
        $points = intdiv((int) ($order->amount_paid ?? 0), 100 * $divisor);
        if ($points < 1) {
            $points = 0;
        }

        if ($points > 0) {
            MiniUserPointsLedger::create([
                'mini_user_id' => (int) $user->id,
                'activity_participation_id' => (int) $part->id,
                'delta_points' => (int) $points,
                'reason' => 'activity_points_claim',
            ]);
        }

        $part->claimed_points_at = $now;
        $part->save();

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'claimed_points' => (int) $points,
            ],
        ]);
    }
}

