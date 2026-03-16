<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\MiniUser;
use app\model\PondSeat;
use app\model\PondFeeRule;
use app\model\FishingOrder;
use think\response\Json;

/**
 * 开钓单（经营链路）：列表/详情（只读为主）
 */
class FishingSessionController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/sessions
     * 可选参数：
     * - page, limit
     * - status       ongoing/finished/settled/cancelled
     * - venue_id
     * - pond_id
     * - seat_code
     * - session_no
     */
    public function list(): Json
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed !== null && empty($allowed)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);

        $status = trim((string) $this->request->get('status', ''));
        $venueId = (int) $this->request->get('venue_id', 0);
        $pondId = (int) $this->request->get('pond_id', 0);
        $seatCode = trim((string) $this->request->get('seat_code', ''));
        $sessionNo = trim((string) $this->request->get('session_no', ''));

        $query = FishingSession::order('id', 'desc');

        if ($allowed !== null) {
            $query->whereIn('pond_id', $allowed);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($venueId > 0) {
            $query->where('venue_id', $venueId);
        }
        if ($pondId > 0) {
            $query->where('pond_id', $pondId);
        }
        if ($seatCode !== '') {
            $query->where('seat_code', $seatCode);
        }
        if ($sessionNo !== '') {
            $query->where('session_no', $sessionNo);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows = $paginator->items();

        // 预加载关联名称，避免 N+1
        $pondIds = [];
        $venueIds = [];
        $userIds = [];
        foreach ($rows as $r) {
            if ($r->pond_id) $pondIds[] = (int) $r->pond_id;
            if ($r->venue_id) $venueIds[] = (int) $r->venue_id;
            if ($r->mini_user_id) $userIds[] = (int) $r->mini_user_id;
        }
        $pondIds = array_values(array_unique($pondIds));
        $venueIds = array_values(array_unique($venueIds));
        $userIds = array_values(array_unique($userIds));

        $pondMap = $pondIds ? FishingPond::whereIn('id', $pondIds)->column('name', 'id') : [];
        $venueMap = $venueIds ? FishingVenue::whereIn('id', $venueIds)->column('name', 'id') : [];
        $userMap = $userIds ? MiniUser::whereIn('id', $userIds)->column('nickname', 'id') : [];

        $list = [];
        foreach ($rows as $r) {
            $arr = $r->toArray();
            $arr['venue_name'] = $arr['venue_id'] ? ($venueMap[$arr['venue_id']] ?? '') : '';
            $arr['pond_name'] = $arr['pond_id'] ? ($pondMap[$arr['pond_id']] ?? '') : '';
            $arr['user_nickname'] = $arr['mini_user_id'] ? ($userMap[$arr['mini_user_id']] ?? '') : '';
            $arr['amount_total_yuan'] = round(((int) ($arr['amount_total'] ?? 0)) / 100, 2);
            $arr['amount_paid_yuan'] = round(((int) ($arr['amount_paid'] ?? 0)) / 100, 2);
            $arr['deposit_total_yuan'] = round(((int) ($arr['deposit_total'] ?? 0)) / 100, 2);
            $list[] = $arr;
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $list,
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * 详情 GET /api/admin/sessions/:id
     */
    public function detail(int $id): Json
    {
        $row = FishingSession::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限查看该池塘数据', 'data' => null]);
        }
        $arr = $row->toArray();
        $arr['amount_total_yuan'] = round(((int) ($arr['amount_total'] ?? 0)) / 100, 2);
        $arr['amount_paid_yuan'] = round(((int) ($arr['amount_paid'] ?? 0)) / 100, 2);
        $arr['deposit_total_yuan'] = round(((int) ($arr['deposit_total'] ?? 0)) / 100, 2);
        return json(['code' => 0, 'msg' => 'success', 'data' => $arr]);
    }

    /**
     * 手动开钓单：POST /api/admin/sessions
     * body: mini_user_id, venue_id, pond_id, seat_id?, fee_rule_id, use_balance?, remark?
     */
    public function create(): Json
    {
        $miniUserId = (int) $this->request->post('mini_user_id', 0);
        $venueId    = (int) $this->request->post('venue_id', 0);
        $pondId     = (int) $this->request->post('pond_id', 0);
        $seatId     = (int) $this->request->post('seat_id', 0);
        $feeRuleId  = (int) $this->request->post('fee_rule_id', 0);
        $useBalance = (bool) $this->request->post('use_balance', false);
        $remark     = trim((string) $this->request->post('remark', ''));

        if ($miniUserId < 1) {
            return json(['code' => 400, 'msg' => '请选择小程序用户', 'data' => null]);
        }
        if ($venueId < 1 || $pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择钓场和池塘', 'data' => null]);
        }
        /** @var MiniUser|null $user */
        $user = MiniUser::find($miniUserId);
        if (!$user) {
            return json(['code' => 404, 'msg' => '小程序用户不存在', 'data' => null]);
        }
        if (!FishingVenue::find($venueId)) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        /** @var FishingPond|null $pond */
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $seatNo = null;
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
            $seatNo = (int) $seat->seat_no;
            $seatCode = (string) $seat->code;

            // 校验该钓位是否已有未结束的开钓单，避免重复绑定
            $exists = FishingSession::where('seat_id', $seatId)
                ->where('status', 'ongoing')
                ->find();
            if ($exists) {
                return json([
                    'code' => 400,
                    'msg'  => '该钓位当前已有开钓单，不能重复绑定，请先结束或取消原开钓单',
                    'data' => null,
                ]);
            }
        }

        if ($feeRuleId < 1) {
            return json(['code' => 400, 'msg' => '请选择收费规则（正钓/偷驴）', 'data' => null]);
        }

        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return json(['code' => 404, 'msg' => '收费规则不存在', 'data' => null]);
        }
        if ((int) $fee->pond_id !== $pondId) {
            return json(['code' => 400, 'msg' => '收费规则不属于当前池塘', 'data' => null]);
        }

        // 应收金额、押金：严格按收费规则计算（不允许自定义）
        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositTotalFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $amountTotalFen = max(0, $amountFen + $depositTotalFen);

        // 自动到期时间：start_time + duration_value/unit
        $expireTime = null;
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                $expireTime = date('Y-m-d H:i:s', time() + $seconds);
            }
        }

        // 会员余额抵扣
        $balanceDeductFen = 0;
        $needPayFen = $amountTotalFen;
        if ($amountTotalFen > 0 && $useBalance && (int) ($user->is_vip ?? 0) === 1) {
            $userBalanceFen = (int) round(((float) ($user->balance ?? 0)) * 100);
            if ($userBalanceFen > 0) {
                $balanceDeductFen = min($userBalanceFen, $amountTotalFen);
                $needPayFen = $amountTotalFen - $balanceDeductFen;
                // 更新用户余额（简单减掉，后续可加余额流水表）
                $user->balance = max(0, ((float) $user->balance) - $balanceDeductFen / 100);
                $user->save();
            }
        }

        // 此处不再直接创建开钓单，而是仅创建待支付订单，
        // 真正的 fishing_session 在支付成功回调中生成（开单前必须先支付）。

        $orderNo = 'O' . date('YmdHis') . mt_rand(1000, 9999);
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

        $amountYuanNeed = round($needPayFen / 100, 2);
        $miniPayPath = '/pages/pay/index?order_no=' . $orderNo . '&amount=' . $amountYuanNeed;

        $resp = [
            'balance_deduct' => round($balanceDeductFen / 100, 2),
            'need_pay'       => round($needPayFen / 100, 2),
            'order'          => $order ? $order->toArray() : null,
            'mini_pay_path'  => $miniPayPath,
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $resp]);
    }

    /**
     * 管理端手动结束开钓单（设为 finished 并写入 end_time）
     * PUT /api/admin/sessions/:id/finish
     */
    public function finish(int $id): Json
    {
        /** @var FishingSession|null $session */
        $session = FishingSession::find($id);
        if (!$session) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $session->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限操作该池塘开钓单', 'data' => null]);
        }
        if ($session->status !== 'ongoing') {
            return json(['code' => 400, 'msg' => '仅进行中的开钓单可结束', 'data' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $session->status = 'finished';
        $session->end_time = $now;
        $session->save();

        return json(['code' => 0, 'msg' => '开钓单已结束', 'data' => $session->toArray()]);
    }

    /**
     * 管理端手动取消开钓单（设为 cancelled 并写入 end_time）
     * PUT /api/admin/sessions/:id/cancel
     */
    public function cancel(int $id): Json
    {
        /** @var FishingSession|null $session */
        $session = FishingSession::find($id);
        if (!$session) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $session->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限操作该池塘开钓单', 'data' => null]);
        }
        if ($session->status !== 'ongoing') {
            return json(['code' => 400, 'msg' => '仅进行中的开钓单可取消', 'data' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $session->status = 'cancelled';
        $session->end_time = $now;
        $session->save();

        return json(['code' => 0, 'msg' => '开钓单已取消', 'data' => $session->toArray()]);
    }
}

