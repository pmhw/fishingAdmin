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
use app\model\MiniUser;
use app\model\MiniUserPointsLedger;
use app\service\ActivityPayService;
use think\facade\Db;
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
 *
 * 小程序调用顺序建议：
 * 1) GET /api/mini/activities 或带 venue_id / pond_id 筛选
 * 2) GET /api/mini/activities/:id 看详情（含 fee_rules 收费档位）
 *    或 GET /api/mini/activities/:id/fee-rules 仅拉收费档位
 * 3) self_pick 时 GET /api/mini/activities/:id/available-seats 选座
 * 4) POST .../participate（可选 use_balance；仅活动 allow_balance_deduct=1 时与开钓单一致：会员免押金+余额先扣）
 * 5) need_pay>0 时 POST /api/mini/pay/wechat/jsapi 传 order_no、description=「活动报名预付款」发起支付；否则已余额付清并已走支付后逻辑
 * 6) GET /api/mini/activities/:id/my 查本人报名与支付状态
 */
class ActivityController extends MiniBaseController
{
    private const ORDER_DESC = '活动报名预付款';

    /**
     * 活动列表（仅已发布）
     *
     * GET /api/mini/activities
     * query: venue_id?, pond_id?（可选，用于筛选）
     */
    public function list(): Json
    {
        $venueId = $this->request->get('venue_id');
        $pondId = $this->request->get('pond_id');
        $venueId = $venueId !== null && $venueId !== '' ? (int) $venueId : 0;
        $pondId = $pondId !== null && $pondId !== '' ? (int) $pondId : 0;

        $query = Activity::where('status', 'published')->order('open_time', 'asc')->order('id', 'desc');

        if ($pondId > 0) {
            $query->where('pond_id', $pondId);
        } elseif ($venueId > 0) {
            $pondIds = FishingPond::where('venue_id', $venueId)->column('id');
            $pondIds = array_values(array_unique(array_map('intval', is_array($pondIds) ? $pondIds : [])));
            if (empty($pondIds)) {
                return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
            }
            $query->whereIn('pond_id', $pondIds);
        }

        $rows = $query->select();
        $now = date('Y-m-d H:i:s');
        $list = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $pond = FishingPond::with('venue')->find((int) ($row->pond_id ?? 0));
            $arr['pond_name'] = $pond ? (string) ($pond->name ?? '') : '';
            $arr['venue_id'] = $pond ? (int) ($pond->venue_id ?? 0) : 0;
            $arr['venue_name'] = $pond && $pond->venue ? (string) ($pond->venue->name ?? '') : '';
            $deadline = (string) ($row->register_deadline ?? '');
            $openTime = (string) ($row->open_time ?? '');
            $arr['can_signup'] = $now <= $deadline && $now < $openTime;
            $paidCount = ActivityParticipation::where('activity_id', (int) $row->id)
                ->where('pay_status', 'paid')
                ->count();
            $arr['paid_count'] = (int) $paidCount;
            $limit = (int) ($row->participant_count ?? 0);
            $arr['quota_full'] = $limit > 0 && $paidCount >= $limit;
            if ($arr['quota_full']) {
                $arr['can_signup'] = false;
            }
            $list[] = $arr;
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => count($list)]]);
    }

    /**
     * 活动详情 + 收费规则（仅已发布）
     *
     * GET /api/mini/activities/:id
     */
    public function detail(int $id): Json
    {
        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => '活动不存在', 'data' => null]);
        }
        /** @var Activity|null $activity */
        $activity = Activity::find($activityId);
        if (!$activity || (string) ($activity->status ?? '') !== 'published') {
            return json(['code' => 404, 'msg' => '活动不存在或未发布', 'data' => null]);
        }

        $data = $activity->toArray();
        $pond = FishingPond::with('venue')->find((int) ($activity->pond_id ?? 0));
        $data['pond_name'] = $pond ? (string) ($pond->name ?? '') : '';
        $data['venue_id'] = $pond ? (int) ($pond->venue_id ?? 0) : 0;
        $data['venue_name'] = $pond && $pond->venue ? (string) ($pond->venue->name ?? '') : '';

        $data['fee_rules'] = $this->loadActivityFeeRulesRows($activityId);

        $now = date('Y-m-d H:i:s');
        $deadline = (string) ($activity->register_deadline ?? '');
        $openTime = (string) ($activity->open_time ?? '');
        $data['can_signup'] = $now <= $deadline && $now < $openTime;
        $paidCount = ActivityParticipation::where('activity_id', $activityId)->where('pay_status', 'paid')->count();
        $data['paid_count'] = (int) $paidCount;
        $limit = (int) ($activity->participant_count ?? 0);
        $data['quota_full'] = $limit > 0 && $paidCount >= $limit;
        if ($data['quota_full']) {
            $data['can_signup'] = false;
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * 活动收费档位（与后台「活动收费规则」一致，pond_fee_rule.activity_id = 活动 id）
     *
     * GET /api/mini/activities/:id/fee-rules
     * data.fee_rules：每条含 id、name、duration、duration_value、duration_unit、amount、deposit、sort_order 等
     */
    public function feeRules(int $id): Json
    {
        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => '活动不存在', 'data' => null]);
        }
        $activity = Activity::find($activityId);
        if (!$activity || (string) ($activity->status ?? '') !== 'published') {
            return json(['code' => 404, 'msg' => '活动不存在或未发布', 'data' => null]);
        }

        $feeRules = $this->loadActivityFeeRulesRows($activityId);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'fee_rules' => $feeRules,
                'total'     => count($feeRules),
            ],
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadActivityFeeRulesRows(int $activityId): array
    {
        $feeRules = PondFeeRule::where('activity_id', $activityId)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();

        return array_map(fn ($r) => $r->toArray(), $feeRules->all());
    }

    /**
     * 自选号码模式下：当前可选 seat_no 列表
     *
     * GET /api/mini/activities/:id/available-seats
     */
    public function availableSeats(int $id): Json
    {
        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => '活动不存在', 'data' => null]);
        }
        $activity = Activity::find($activityId);
        if (!$activity || (string) ($activity->status ?? '') !== 'published') {
            return json(['code' => 404, 'msg' => '活动不存在或未发布', 'data' => null]);
        }
        if ((string) ($activity->draw_mode ?? '') !== 'self_pick') {
            return json(['code' => 400, 'msg' => '该活动不是自选号码模式', 'data' => ['seats' => []]]);
        }

        $pondId = (int) ($activity->pond_id ?? 0);
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '活动池塘缺失', 'data' => null]);
        }

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

        $seats = PondSeat::where('pond_id', $pondId)->order('seat_no', 'asc')->select();
        $out = [];
        foreach ($seats as $s) {
            $sid = (int) ($s->id ?? 0);
            if ($sid < 1) {
                continue;
            }
            if (isset($occupiedSeatIds[$sid]) || isset($assignedSeatIds[$sid])) {
                continue;
            }
            $out[] = [
                'seat_no'   => (int) ($s->seat_no ?? 0),
                'seat_code' => (string) ($s->code ?? ''),
            ];
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['seats' => $out, 'total' => count($out)]]);
    }

    /**
     * 当前登录用户在该活动下的报名/支付/抽号状态
     *
     * GET /api/mini/activities/:id/my
     */
    public function myParticipation(int $id): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $activityId = (int) $id;
        if ($activityId < 1) {
            return json(['code' => 400, 'msg' => '活动不存在', 'data' => null]);
        }
        $activity = Activity::find($activityId);
        if (!$activity) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }

        $part = ActivityParticipation::where('activity_id', $activityId)
            ->where('mini_user_id', (int) $user->id)
            ->find();

        if (!$part) {
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'enrolled' => false,
                    'activity' => [
                        'id' => $activityId,
                        'status' => (string) ($activity->status ?? ''),
                        'draw_mode' => (string) ($activity->draw_mode ?? ''),
                        'unified_draw_enabled' => (int) ($activity->unified_draw_enabled ?? 0),
                    ],
                ],
            ]);
        }

        $orderNo = (string) ($part->pay_order_no ?? '');
        /** @var FishingOrder|null $order */
        $order = $orderNo !== '' ? FishingOrder::where('order_no', $orderNo)->find() : null;

        $canDraw = (string) ($activity->status ?? '') === 'published'
            && (string) ($activity->draw_mode ?? '') === 'unified'
            && (int) ($activity->unified_draw_enabled ?? 0) === 1
            && (string) ($part->pay_status ?? '') === 'paid'
            && empty($part->assigned_seat_id)
            && in_array((string) ($part->draw_status ?? ''), ['draw_waiting_unified', 'waiting_paid'], true);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'enrolled' => true,
                'participation' => $part->toArray(),
                'order' => $order ? [
                    'order_no' => (string) ($order->order_no ?? ''),
                    'status' => (string) ($order->status ?? ''),
                    'amount_total' => (int) ($order->amount_total ?? 0),
                    'amount_paid' => (int) ($order->amount_paid ?? 0),
                    'amount_total_yuan' => round(((int) ($order->amount_total ?? 0)) / 100, 2),
                    'amount_paid_yuan' => round(((int) ($order->amount_paid ?? 0)) / 100, 2),
                ] : null,
                'can_draw' => $canDraw,
                'can_claim_points' => (string) ($activity->status ?? '') === 'published'
                    && (string) ($part->draw_status ?? '') === 'assigned'
                    && empty($part->claimed_points_at)
                    && (int) ($activity->points_divisor ?? 0) > 0,
            ],
        ]);
    }

    /**
     * 活动报名 + 生成支付订单（不直接返回支付参数）
     *
     * POST /api/mini/activities/:id/participate
     * body:
     * - fee_rule_id（pond_fee_rule.id）
     * - desired_seat_no（仅 self_pick 需要）
     * - use_balance（可选，默认 true；仅后台开启「允许余额抵扣」的活动生效，否则全额微信含押金）
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

        $useBalance = $this->request->post('use_balance');
        if ($useBalance === null || $useBalance === '') {
            $useBalance = true;
        } else {
            $useBalance = (bool) $useBalance;
        }
        $allowBalance = (int) ($activity->allow_balance_deduct ?? 1) === 1;
        $useBalanceEffective = $useBalance && $allowBalance;

        $pondId = (int) $activity->pond_id;
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return json(['code' => 404, 'msg' => '活动池塘不存在', 'data' => null]);
        }
        $venueId = (int) ($pond->venue_id ?? 0);
        $limitCount = (int) ($activity->participant_count ?? 0);

        $resultPayload = null;
        try {
            Db::transaction(function () use (
                &$resultPayload,
                $activityId,
                $user,
                $feeRuleId,
                $fee,
                $desiredSeatNo,
                $useBalanceEffective,
                $allowBalance,
                $useBalance,
                $pondId,
                $venueId,
                $limitCount
            ) {
                $miniUserId = (int) $user->id;

                /** @var MiniUser|null $lockedUser */
                $lockedUser = MiniUser::where('id', $miniUserId)->lock(true)->find();
                if (!$lockedUser) {
                    $resultPayload = ['code' => 400, 'msg' => '用户不存在', 'data' => null];
                    throw new \RuntimeException('__participate_abort');
                }

                if (ActivityParticipation::where('activity_id', $activityId)
                    ->where('mini_user_id', $miniUserId)
                    ->find()) {
                    $resultPayload = ['code' => 400, 'msg' => '你已报名该活动', 'data' => null];
                    throw new \RuntimeException('__participate_abort');
                }

                if ($limitCount > 0) {
                    $paidCount = ActivityParticipation::where('activity_id', $activityId)
                        ->where('pay_status', 'paid')
                        ->count();
                    if ((int) $paidCount >= $limitCount) {
                        $resultPayload = ['code' => 400, 'msg' => '活动名额已满', 'data' => null];
                        throw new \RuntimeException('__participate_abort');
                    }
                }

                $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
                $depositRawFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
                $depositWaivedFlag = 0;
                $depositEffectiveFen = $depositRawFen;
                if ((int) ($lockedUser->is_vip ?? 0) === 1 && $useBalanceEffective && $depositRawFen > 0) {
                    $depositEffectiveFen = 0;
                    $depositWaivedFlag = 1;
                }
                $amountTotalFen = max(0, $amountFen + $depositEffectiveFen);
                if ($amountTotalFen <= 0) {
                    $resultPayload = ['code' => 400, 'msg' => '活动收费金额异常', 'data' => null];
                    throw new \RuntimeException('__participate_abort');
                }

                $seatId = null;
                $seatNo = null;
                $seatCode = null;
                if ($desiredSeatNo !== null) {
                    /** @var PondSeat|null $seat */
                    $seat = PondSeat::where('pond_id', $pondId)->where('seat_no', $desiredSeatNo)->find();
                    if (!$seat) {
                        $resultPayload = ['code' => 400, 'msg' => '该座位号不存在', 'data' => null];
                        throw new \RuntimeException('__participate_abort');
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
                        $resultPayload = ['code' => 400, 'msg' => '该座位已被占用或已分配，请刷新再选', 'data' => null];
                        throw new \RuntimeException('__participate_abort');
                    }
                }

                $balanceDeductFen = 0;
                $needPayFen = $amountTotalFen;
                if ($amountTotalFen > 0 && $useBalanceEffective && (int) ($lockedUser->is_vip ?? 0) === 1) {
                    $userBalanceFen = (int) round(((float) ($lockedUser->balance ?? 0)) * 100);
                    if ($userBalanceFen > 0) {
                        $balanceDeductFen = min($userBalanceFen, $amountTotalFen);
                        $needPayFen = $amountTotalFen - $balanceDeductFen;
                        $lockedUser->balance = max(0, ((float) $lockedUser->balance) - $balanceDeductFen / 100);
                        $lockedUser->save();
                    }
                }

                $orderNo = 'A' . date('YmdHis') . mt_rand(1000, 9999) . random_int(100, 999);

                $payStatus = $needPayFen > 0 ? 'pending' : 'paid';
                $participation = ActivityParticipation::create([
                    'activity_id'          => $activityId,
                    'mini_user_id'         => $miniUserId,
                    'fee_rule_id'          => $feeRuleId,
                    'pay_order_no'         => $orderNo,
                    'balance_deduct_fen'   => $balanceDeductFen,
                    'deposit_waived'       => $depositWaivedFlag,
                    'pay_status'           => $payStatus,
                    'draw_status'          => 'waiting_paid',
                    'desired_seat_no'      => $desiredSeatNo,
                    'assigned_seat_id'     => null,
                    'assigned_seat_no'     => null,
                    'assigned_session_id'  => null,
                ]);

                if ($needPayFen > 0) {
                    FishingOrder::create([
                        'order_no'       => $orderNo,
                        'mini_user_id'   => $miniUserId,
                        'venue_id'       => $venueId > 0 ? $venueId : null,
                        'pond_id'        => $pondId > 0 ? $pondId : null,
                        'seat_id'        => $seatId ? (int) $seatId : null,
                        'seat_no'        => $seatNo ? (int) $seatNo : null,
                        'seat_code'      => $seatCode !== '' ? (string) $seatCode : null,
                        'fee_rule_id'    => $feeRuleId,
                        'return_rule_id' => null,
                        'description'    => self::ORDER_DESC,
                        'amount_total'   => $needPayFen,
                        'amount_paid'    => 0,
                        'status'         => 'pending',
                        'pay_channel'    => 'wx_mini',
                        'raw_notify'     => null,
                    ]);
                } else {
                    /** @var FishingOrder $paidOrder */
                    $paidOrder = FishingOrder::create([
                        'order_no'       => $orderNo,
                        'mini_user_id'   => $miniUserId,
                        'venue_id'       => $venueId > 0 ? $venueId : null,
                        'pond_id'        => $pondId > 0 ? $pondId : null,
                        'seat_id'        => $seatId ? (int) $seatId : null,
                        'seat_no'        => $seatNo ? (int) $seatNo : null,
                        'seat_code'      => $seatCode !== '' ? (string) $seatCode : null,
                        'fee_rule_id'    => $feeRuleId,
                        'return_rule_id' => null,
                        'description'    => self::ORDER_DESC,
                        'amount_total'   => $amountTotalFen,
                        'amount_paid'    => $amountTotalFen,
                        'status'         => 'paid',
                        'pay_channel'    => 'balance',
                        'pay_time'       => date('Y-m-d H:i:s'),
                        'raw_notify'     => null,
                    ]);
                    ActivityPayService::processAfterPaid($paidOrder);
                }

                $amountStr = number_format(round($needPayFen / 100, 2), 2, '.', '');
                $miniPayPath = $needPayFen > 0
                    ? '/pages/pay/index?order_no=' . $orderNo . '&amount=' . $amountStr
                    : null;

                $resultPayload = [
                    'code' => 0,
                    'msg'  => 'success',
                    'data' => [
                        'order_no'              => $orderNo,
                        'amount_total_yuan'     => round($amountTotalFen / 100, 2),
                        'need_pay_yuan'         => round($needPayFen / 100, 2),
                        'balance_deduct_yuan'   => round($balanceDeductFen / 100, 2),
                        'balance_deduct'        => number_format($balanceDeductFen / 100, 2, '.', ''),
                        'need_pay'              => number_format($needPayFen / 100, 2, '.', ''),
                        'mini_pay_path'         => $miniPayPath,
                        'participation_id'      => (int) $participation->id,
                        'description'           => self::ORDER_DESC,
                        'allow_balance_deduct'  => $allowBalance,
                        'use_balance_requested' => $useBalance,
                        'use_balance_applied'   => $useBalanceEffective,
                    ],
                ];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__participate_abort') {
                return json(['code' => 500, 'msg' => '报名失败，请重试', 'data' => null]);
            }
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => '报名失败，请重试', 'data' => null]);
        }

        if ($resultPayload === null) {
            return json(['code' => 500, 'msg' => '报名失败', 'data' => null]);
        }
        if (($resultPayload['code'] ?? 0) !== 0) {
            return json($resultPayload);
        }

        return json($resultPayload);
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
        $money = ActivityPayService::sessionMoneyForActivity($order, $part, $fee);

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
            'amount_total'  => $money['amount_total_fen'],
            'amount_paid'   => $money['amount_paid_fen'],
            'deposit_total' => $money['deposit_total_fen'],
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

        // points_divisor 语义：每 1 元实付可获得多少积分（例：10 表示 1 元=10 积分）
        // 为 0 时不发放积分，且不记录「已领取」以免占用资格
        $pointsPerYuan = (int) ($activity->points_divisor ?? 0);
        if ($pointsPerYuan <= 0) {
            return json(['code' => 400, 'msg' => '本活动未开启积分发放（请将「1元积分」设为大于 0）', 'data' => null]);
        }

        $amountPaidFen = (int) ($order->amount_paid ?? 0) + (int) ($part->balance_deduct_fen ?? 0);
        $points = (int) floor(($amountPaidFen / 100.0) * $pointsPerYuan);
        if ($points < 0) {
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

