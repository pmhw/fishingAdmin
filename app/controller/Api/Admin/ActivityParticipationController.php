<?php
declare(strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use think\facade\Db;
use think\response\Json;

/**
 * 活动参与 / 抽号记录（只读列表）
 */
class ActivityParticipationController extends BaseController
{
    use PondScopeTrait;

    /**
     * GET /api/admin/activity-participations
     *
     * query:
     * - page, limit
     * - activity_id
     * - venue_id（钓场，关联活动池塘）
     * - pay_status: pending|paid|failed
     * - draw_status
     * - pay_order_no（模糊或精确：此处用 whereLike）
     */
    public function list(): Json
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed !== null && empty($allowed)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $page = max(1, (int) $this->request->get('page', 1));
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $activityId = (int) $this->request->get('activity_id', 0);
        $payStatus = trim((string) $this->request->get('pay_status', ''));
        $drawStatus = trim((string) $this->request->get('draw_status', ''));
        $venueId = $this->request->get('venue_id');
        $venueId = $venueId !== null && $venueId !== '' ? (int) $venueId : 0;
        $orderNo = trim((string) $this->request->get('pay_order_no', ''));

        $query = Db::name('activity_participation')->alias('p')
            ->join('activity a', 'p.activity_id = a.id')
            ->leftJoin('mini_user u', 'p.mini_user_id = u.id')
            ->leftJoin('fishing_pond pond', 'a.pond_id = pond.id')
            ->leftJoin('fishing_venue v', 'pond.venue_id = v.id')
            ->leftJoin('pond_fee_rule fr', 'p.fee_rule_id = fr.id')
            ->field(
                'p.id,p.activity_id,p.mini_user_id,p.fee_rule_id,p.pay_order_no,p.pay_status,p.draw_status,' .
                'p.desired_seat_no,p.assigned_seat_id,p.assigned_seat_no,p.assigned_session_id,p.claimed_points_at,' .
                'p.created_at,p.updated_at,' .
                'a.name as activity_name,a.status as activity_status,a.draw_mode as activity_draw_mode,' .
                'pond.name as pond_name,v.name as venue_name,u.nickname as user_nickname,fr.name as fee_rule_name'
            );

        if ($allowed !== null) {
            $query->whereIn('a.pond_id', $allowed);
        }
        if ($activityId > 0) {
            $query->where('p.activity_id', $activityId);
        }
        if ($venueId > 0) {
            $query->where('pond.venue_id', $venueId);
        }
        if ($payStatus !== '') {
            $query->where('p.pay_status', $payStatus);
        }
        if ($drawStatus !== '') {
            $query->where('p.draw_status', $drawStatus);
        }
        if ($orderNo !== '') {
            $query->whereLike('p.pay_order_no', '%' . addcslashes($orderNo, '%_\\') . '%');
        }

        $query->order('p.id', 'desc');

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $list = $paginator->items();

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $list,
                'total' => $paginator->total(),
            ],
        ]);
    }
}
