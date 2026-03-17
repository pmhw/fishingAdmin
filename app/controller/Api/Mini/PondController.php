<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingPond;
use app\model\FishingSession;
use app\model\PondFeeRule;
use app\model\PondReturnLog;
use app\model\PondSeat;
use think\response\Json;

/**
 * 小程序端 - 池塘详情（收费规则 + 钓位）
 *
 * GET /api/mini/ponds/:pond_id
 * 用途：pages/session-open/index 打开时，获取当前池塘的可选收费规则和钓位列表。
 */
class PondController extends MiniBaseController
{
    /**
     * 池塘详情：id, name, venue_id, fee_rules, seats
     */
    public function detail(int $id): Json
    {
        /** @var FishingPond|null $pond */
        $pond = FishingPond::where('id', $id)->where('status', 'open')->find();
        if (!$pond) {
            return json(['code' => 404, 'msg' => '池塘不存在或已关闭', 'data' => null]);
        }

        $data = [
            'id'        => (int) $pond->id,
            'name'      => (string) ($pond->name ?? ''),
            'venue_id'  => (int) $pond->venue_id,
            'fee_rules' => [],
            'seats'     => [],
            'current_session_return_tiao_qty' => 0,
        ];

        // 收费规则：id, name, amount_yuan, deposit_yuan, duration（数值，小时）
        $feeRules = PondFeeRule::where('pond_id', $id)->order('sort_order', 'asc')->order('id', 'asc')->select();
        foreach ($feeRules as $row) {
            $amountYuan = number_format((float) ($row->amount ?? 0), 2, '.', '');
            $depositYuan = number_format((float) ($row->deposit ?? 0), 2, '.', '');
            $duration = 0;
            $val = $row->duration_value !== null ? (float) $row->duration_value : 0;
            $unit = (string) ($row->duration_unit ?? '');
            if ($unit === 'hour') {
                $duration = (int) round($val);
            } elseif ($unit === 'day') {
                $duration = (int) round($val * 24);
            }
            $data['fee_rules'][] = [
                'id'           => (int) $row->id,
                'name'         => (string) ($row->name ?? ''),
                'amount_yuan'  => $amountYuan,
                'deposit_yuan' => $depositYuan,
                'duration'     => $duration,
            ];
        }

        // 当前池塘下进行中开钓单占用的钓位 id 列表
        $occupiedSeatIds = FishingSession::where('pond_id', $id)
            ->where('status', 'ongoing')
            ->whereNotNull('seat_id')
            ->where('seat_id', '>', 0)
            ->column('seat_id');
        $occupiedSeatIds = array_flip(array_map('intval', $occupiedSeatIds));

        // 钓位：id, name（如 "1 号"）, code, occupied（是否被占用）
        $seats = PondSeat::where('pond_id', $id)->order('seat_no', 'asc')->select();
        foreach ($seats as $row) {
            $name = $row->seat_no !== null && $row->seat_no !== ''
                ? ($row->seat_no . ' 号')
                : ((string) ($row->code ?? ''));
            $data['seats'][] = [
                'id'       => (int) $row->id,
                'name'     => $name,
                'code'     => (string) ($row->code ?? ''),
                'occupied' => isset($occupiedSeatIds[(int) $row->id]),
            ];
        }

        // 当前登录用户在该池塘下“进行中”的开钓单，对应的回鱼条数（仅按条统计）
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($user) {
            $session = FishingSession::where('mini_user_id', (int) $user->id)
                ->where('pond_id', $id)
                ->where('status', 'ongoing')
                ->order('id', 'desc')
                ->find();
            if ($session) {
                $tiaoQty = (float) PondReturnLog::where('session_id', (int) $session->id)
                    ->where('return_type', 'tiao')
                    ->sum('qty');
                $data['current_session_return_tiao_qty'] = $tiaoQty;
            }
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }
}
