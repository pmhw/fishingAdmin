<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\MiniUser;
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
}

