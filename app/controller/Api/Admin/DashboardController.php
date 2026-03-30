<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\AdminUser;
use app\model\FishingOrder;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\PondReturnLog;
use think\facade\Db;
use think\response\Json;

/**
 * 首页数据看板
 * GET /api/admin/dashboard/stats?venue_id=0
 * - venue_id=0 或未传：全部钓场（仍受池塘数据范围约束）
 * - venue_id>0：指定钓场
 *
 * 未在 admin_permission 中配置时，仅需登录即可访问。
 */
class DashboardController extends BaseController
{
    use PondScopeTrait;
    use VenueScopeTrait;

    /**
     * 统计用池塘范围：
     * - null：不限制池塘（非池塘管理员或超管）
     * - []：无可见池塘
     * - [id,...]：仅这些池塘
     */
    private function getStatsPondScope(): ?array
    {
        $adminId = (int) ($this->request->adminId ?? 0);
        if ($adminId < 1) {
            return [];
        }
        $user = AdminUser::with('role')->find($adminId);
        if (!$user || !$user->role) {
            return [];
        }
        $codes = $user->getPermissionCodes();
        if (in_array('*', $codes, true)) {
            return null;
        }
        if (in_array('admin.pond.manage', $codes, true)) {
            return $this->getAdminAllowedPondIds();
        }

        return null;
    }

    private function emptyPayload(int $venueId, string $scopeLabel): array
    {
        return [
            'venue_id'               => $venueId,
            'scope_label'            => $scopeLabel,
            'card_order_paid_yuan'   => 0.0,
            'session_paid_yuan'      => 0.0,
            'consumer_user_count'    => 0,
            'vip_user_count'         => 0,
            'return_log_count'       => 0,
            'return_jin_qty'         => 0.0,
            'return_tiao_qty'        => 0.0,
            'return_amount_yuan'     => 0.0,
            'session_total_count'    => 0,
            'session_ongoing_count'  => 0,
            'session_timeout_count'  => 0,
            'card_order_count'       => 0,
            'venue_name'             => '',
        ];
    }

    public function stats(): Json
    {
        $venueId = (int) $this->request->get('venue_id', 0);
        if ($venueId > 0 && !$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权查看该钓场统计', 'data' => null]);
        }

        $pondScope = $this->getStatsPondScope();
        if ($pondScope !== null && $pondScope === []) {
            $label = $venueId > 0 ? '指定钓场' : '全部钓场';
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => $this->emptyPayload($venueId, $label . '（无池塘权限）'),
            ]);
        }

        $venueName = '';
        if ($venueId > 0) {
            $vn = FishingVenue::find($venueId);
            $venueName = $vn ? (string) ($vn->name ?? '') : '';
        }
        $scopeLabel = $venueId > 0 ? ($venueName !== '' ? $venueName : '指定钓场') : '全部钓场';

        $sessionScoped = function () use ($pondScope, $venueId) {
            // ThinkPHP Model 无 ::query()，用 whereRaw 作为可链式查询起点
            $q = FishingSession::whereRaw('1=1');
            if ($pondScope !== null) {
                $q->whereIn('pond_id', $pondScope);
            }
            if ($venueId > 0) {
                $q->where('venue_id', $venueId);
            }

            return $q;
        };

        // ---------- fishing_session ----------
        $sessionTotalCount   = (int) $sessionScoped()->count();
        $sessionOngoingCount = (int) $sessionScoped()->where('status', 'ongoing')->count();
        $sessionTimeoutCount = (int) $sessionScoped()->where('status', 'timeout')->count();
        $sessionPaidSumFen   = (int) $sessionScoped()->sum('amount_paid');
        $sessionPaidYuan     = round($sessionPaidSumFen / 100, 2);

        // 有过开钓单的去重用户数
        $consumerRow = Db::name('fishing_session')
            ->when($pondScope !== null, static function ($q) use ($pondScope) {
                $q->whereIn('pond_id', $pondScope);
            })
            ->when($venueId > 0, static function ($q) use ($venueId) {
                $q->where('venue_id', $venueId);
            })
            ->fieldRaw('COUNT(DISTINCT mini_user_id) AS cnt')
            ->find();
        $consumerUserCount = (int) ($consumerRow['cnt'] ?? 0);

        // 其中会员（is_vip=1）去重用户
        $vipRow = Db::name('fishing_session')->alias('s')
            ->join(['mini_user' => 'u'], 'u.id = s.mini_user_id')
            ->where('u.is_vip', 1);
        if ($pondScope !== null) {
            $vipRow->whereIn('s.pond_id', $pondScope);
        }
        if ($venueId > 0) {
            $vipRow->where('s.venue_id', $venueId);
        }
        $vipAgg = $vipRow->fieldRaw('COUNT(DISTINCT s.mini_user_id) AS cnt')->find();
        $vipUserCount = (int) ($vipAgg['cnt'] ?? 0);

        // ---------- 开卡订单 fishing_order（排除店铺 SO 占位） ----------
        $orderScoped = function () use ($pondScope, $venueId) {
            $q = FishingOrder::where('order_no', 'not like', 'SO%');
            if ($pondScope !== null) {
                $q->whereIn('pond_id', $pondScope);
            }
            if ($venueId > 0) {
                $q->where('venue_id', $venueId);
            }

            return $q;
        };

        $cardOrderCount = (int) $orderScoped()->count();
        $orderPaidFen   = (int) $orderScoped()->where('status', 'paid')->sum('amount_paid');
        $cardOrderPaidYuan = round($orderPaidFen / 100, 2);

        // ---------- 回鱼 pond_return_log ----------
        $returnScoped = function () use ($pondScope, $venueId) {
            $q = PondReturnLog::whereRaw('1=1');
            if ($pondScope !== null) {
                $q->whereIn('pond_id', $pondScope);
            }
            if ($venueId > 0) {
                $q->where('venue_id', $venueId);
            }

            return $q;
        };

        $returnLogCount = (int) $returnScoped()->count();
        $jinQty         = (float) $returnScoped()->where('return_type', 'jin')->sum('qty');
        $tiaoQty        = (float) $returnScoped()->where('return_type', 'tiao')->sum('qty');
        $returnAmount   = (float) $returnScoped()->sum('amount');

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'venue_id'              => $venueId,
                'venue_name'            => $venueName,
                'scope_label'           => $scopeLabel,
                'card_order_paid_yuan'  => $cardOrderPaidYuan,
                'session_paid_yuan'     => $sessionPaidYuan,
                'consumer_user_count'   => $consumerUserCount,
                'vip_user_count'        => $vipUserCount,
                'return_log_count'      => $returnLogCount,
                'return_jin_qty'        => round($jinQty, 2),
                'return_tiao_qty'       => round($tiaoQty, 2),
                'return_amount_yuan'    => round($returnAmount, 2),
                'session_total_count'   => $sessionTotalCount,
                'session_ongoing_count'   => $sessionOngoingCount,
                'session_timeout_count'   => $sessionTimeoutCount,
                'card_order_count'      => $cardOrderCount,
            ],
        ]);
    }
}
