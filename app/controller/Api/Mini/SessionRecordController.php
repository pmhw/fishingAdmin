<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingPond;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\PondReturnLog;
use think\facade\Db;
use think\response\Json;

/**
 * 小程序端 - 垂钓记录
 *
 * GET /api/mini/session-records
 * Header: Authorization: Bearer {token}
 *
 * 返回：
 * - total_count：开钓单总数量
 * - total_weight_jin：回鱼总重量（斤，仅统计 return_type=jin）
 * - list：分页记录（每条含本单回鱼重量/金额等聚合）
 */
class SessionRecordController extends MiniBaseController
{
    public function list(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }

        $page  = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 50);
        $status = trim((string) $this->request->get('status', '')); // 可选：ongoing/finished/settled/cancelled

        $miniUserId = (int) $user->id;

        // 总开钓数量
        $countQuery = FishingSession::where('mini_user_id', $miniUserId);
        if ($status !== '') {
            $countQuery->where('status', $status);
        }
        $totalCount = (int) $countQuery->count();

        // 总回鱼重量（斤）：只统计 return_type=jin
        $totalWeight = (float) Db::table('pond_return_log')
            ->alias('r')
            ->join(['fishing_session' => 's'], 's.id = r.session_id')
            ->where('s.mini_user_id', $miniUserId)
            ->when($status !== '', function ($q) use ($status) {
                $q->where('s.status', $status);
            })
            ->where('r.return_type', 'jin')
            ->sum('r.qty');
        $totalWeight = round((float) $totalWeight, 2);

        // 列表分页
        $query = FishingSession::where('mini_user_id', $miniUserId)->order('id', 'desc');
        if ($status !== '') {
            $query->where('status', $status);
        }
        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows = $paginator->items();

        $sessionIds = [];
        $venueIds = [];
        $pondIds = [];
        foreach ($rows as $r) {
            $sessionIds[] = (int) $r->id;
            if ($r->venue_id) $venueIds[] = (int) $r->venue_id;
            if ($r->pond_id) $pondIds[] = (int) $r->pond_id;
        }
        $sessionIds = array_values(array_unique($sessionIds));
        $venueIds = array_values(array_unique($venueIds));
        $pondIds = array_values(array_unique($pondIds));

        $venueMap = $venueIds ? FishingVenue::whereIn('id', $venueIds)->column('name', 'id') : [];
        $pondMap  = $pondIds ? FishingPond::whereIn('id', $pondIds)->column('name', 'id') : [];

        // per-session 回鱼聚合：weight_jin / amount_yuan
        $returnAgg = [];
        if (!empty($sessionIds)) {
            $aggRows = Db::table('pond_return_log')
                ->whereIn('session_id', $sessionIds)
                ->fieldRaw("session_id, SUM(CASE WHEN return_type='jin' THEN qty ELSE 0 END) AS weight_jin, SUM(amount) AS return_amount_yuan")
                ->group('session_id')
                ->select()
                ->all();
            foreach ($aggRows as $a) {
                $sid = (int) ($a['session_id'] ?? 0);
                $returnAgg[$sid] = [
                    'weight_jin' => round((float) ($a['weight_jin'] ?? 0), 2),
                    'amount_yuan' => round((float) ($a['return_amount_yuan'] ?? 0), 2),
                ];
            }
        }

        $list = [];
        foreach ($rows as $r) {
            $arr = $r->toArray();
            $sid = (int) ($arr['id'] ?? 0);
            $arr['venue_name'] = $arr['venue_id'] ? ($venueMap[$arr['venue_id']] ?? '') : '';
            $arr['pond_name']  = $arr['pond_id'] ? ($pondMap[$arr['pond_id']] ?? '') : '';
            $arr['amount_total_yuan'] = round(((int) ($arr['amount_total'] ?? 0)) / 100, 2);
            $arr['amount_paid_yuan']  = round(((int) ($arr['amount_paid'] ?? 0)) / 100, 2);
            $arr['deposit_total_yuan'] = round(((int) ($arr['deposit_total'] ?? 0)) / 100, 2);
            $arr['return_weight_jin'] = $returnAgg[$sid]['weight_jin'] ?? 0;
            $arr['return_amount_yuan'] = $returnAgg[$sid]['amount_yuan'] ?? 0;
            $list[] = $arr;
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'total_count'      => $totalCount,
                'total_weight_jin' => number_format($totalWeight, 2, '.', ''),
                'list'             => $list,
                'total'            => (int) $paginator->total(),
            ],
        ]);
    }
}

