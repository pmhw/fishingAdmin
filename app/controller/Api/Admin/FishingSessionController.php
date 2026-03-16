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
     * body: mini_user_id, venue_id, pond_id, seat_id?, fee_rule_id?, amount_total?(元), deposit_total?(元), use_balance?, remark?
     */
    public function create(): Json
    {
        $miniUserId = (int) $this->request->post('mini_user_id', 0);
        $venueId    = (int) $this->request->post('venue_id', 0);
        $pondId     = (int) $this->request->post('pond_id', 0);
        $seatId     = (int) $this->request->post('seat_id', 0);
        $feeRuleId  = (int) $this->request->post('fee_rule_id', 0);
        $amountYuan = $this->request->post('amount_total');
        $depositYuan = $this->request->post('deposit_total');
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
        }

        // 计算应收金额（分）
        $amountTotalFen = 0;
        if ($amountYuan !== null && $amountYuan !== '') {
            $amountTotalFen = (int) round(((float) $amountYuan) * 100);
        } elseif ($feeRuleId > 0) {
            /** @var PondFeeRule|null $fee */
            $fee = PondFeeRule::find($feeRuleId);
            if ($fee && (int) $fee->amount > 0) {
                $amountTotalFen = (int) round(((float) $fee->amount) * 100);
            }
        }
        $depositTotalFen = 0;
        if ($depositYuan !== null && $depositYuan !== '') {
            $depositTotalFen = (int) round(((float) $depositYuan) * 100);
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

        // 生成 session_no
        $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);

        /** @var FishingSession $session */
        $session = FishingSession::create([
            'session_no'   => $sessionNo,
            'mini_user_id' => $miniUserId,
            'venue_id'     => $venueId,
            'pond_id'      => $pondId,
            'seat_id'      => $seatId ?: null,
            'seat_no'      => $seatNo,
            'seat_code'    => $seatCode,
            'fee_rule_id'  => $feeRuleId ?: null,
            'order_id'     => null,
            'start_time'   => date('Y-m-d H:i:s'),
            'status'       => 'ongoing',
            'amount_total' => $amountTotalFen,
            'amount_paid'  => $balanceDeductFen,
            'deposit_total'=> $depositTotalFen,
            'remark'       => $remark,
        ]);

        $order = null;
        $miniPayPath = null;

        if ($needPayFen > 0) {
            // 创建订单（只记录金额和关联，具体支付仍走小程序 jsapi 接口）
            $orderNo = 'O' . date('YmdHis') . mt_rand(1000, 9999);
            $order = FishingOrder::create([
                'order_no'     => $orderNo,
                'mini_user_id' => $miniUserId,
                'venue_id'     => $venueId,
                'pond_id'      => $pondId,
                'seat_id'      => $seatId ?: null,
                'seat_no'      => $seatNo,
                'seat_code'    => $seatCode,
                'fee_rule_id'  => $feeRuleId ?: null,
                'description'  => '开钓单 ' . $sessionNo,
                'amount_total' => $needPayFen,
                'amount_paid'  => 0,
                'status'       => 'pending',
                'pay_channel'  => 'wx_mini',
            ]);
            // 反写 session.order_id
            $session->order_id = $order->id;
            $session->save();

            $amountYuanNeed = round($needPayFen / 100, 2);
            // 小程序端支付页路由约定
            $miniPayPath = '/pages/pay/index?session_id=' . $session->id . '&order_no=' . $orderNo . '&amount=' . $amountYuanNeed;
        }

        $sessionArr = $session->toArray();
        $sessionArr['amount_total_yuan'] = round($amountTotalFen / 100, 2);
        $sessionArr['amount_paid_yuan'] = round($sessionArr['amount_paid'] / 100, 2);
        $sessionArr['deposit_total_yuan'] = round($depositTotalFen / 100, 2);

        $resp = [
            'session'           => $sessionArr,
            'balance_deduct'    => round($balanceDeductFen / 100, 2),
            'need_pay'          => round($needPayFen / 100, 2),
            'order'             => $order ? $order->toArray() : null,
            'mini_pay_path'     => $miniPayPath,
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $resp]);
    }
}

