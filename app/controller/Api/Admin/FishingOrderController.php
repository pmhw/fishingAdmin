<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingOrder;
use app\model\FishingPond;
use app\model\FishingVenue;
use app\model\MiniUser;
use app\service\MemberBalanceRechargeService;
use think\response\Json;

/**
 * 订单管理（交易中心）：
 * 展示除店铺商品单以外的 fishing_order（开钓预付款、活动报名、会员余额充值等），不支持在后台直接改金额或状态。
 *
 * 说明：店铺商品走微信付时会在 fishing_order 里插入与 venue_shop_order 同号的「占位」行
 *（order_no 以 SO 开头、description 为「店铺订单」），此类不在本列表展示，请用「店铺商品订单」菜单。
 *
 * 数据范围：按管理员角色绑定的钓场（VenueScopeTrait）过滤；无钓场归属的订单（如会员充值 venue_id 为空）一并展示。
 * 前端传入 venue_id 时仅能在已授权钓场内筛选，否则 403。
 */
class FishingOrderController extends BaseController
{
    use VenueScopeTrait;

    /**
     * 列表 GET /api/admin/orders
     *
     * 可选查询参数：
     * - page, limit
     * - status       订单状态：pending / paid / timeout / closed / refund
     * - venue_id     钓场 ID
     * - pond_id      池塘 ID
     * - order_no     精确匹配订单号
     */
    public function list(): Json
    {
        $page    = max((int) $this->request->get('page', 1), 1);
        $limit   = min(max((int) $this->request->get('limit', 10), 1), 100);
        $status  = trim((string) $this->request->get('status', ''));
        $venueId = (int) $this->request->get('venue_id', 0);
        $pondId  = (int) $this->request->get('pond_id', 0);
        $orderNo = trim((string) $this->request->get('order_no', ''));

        $allowedVenues = $this->getAdminAllowedVenueIds();
        if (is_array($allowedVenues) && $allowedVenues === []) {
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => ['list' => [], 'total' => 0],
            ]);
        }

        $query = FishingOrder::order('id', 'desc');

        // 排除店铺商品微信支付在 fishing_order 中的占位行（单号 SO*，与 venue_shop_order 一致）
        if ($orderNo !== '' && str_starts_with($orderNo, 'SO')) {
            $query->whereRaw('1=0');
        } else {
            $query->where('order_no', 'not like', 'SO%');
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($allowedVenues !== null) {
            // 会员充值等订单 venue_id 为空，需与「授权钓场」订单一同可见
            $ids = array_values(array_unique(array_map('intval', $allowedVenues)));
            $ids = array_values(array_filter($ids, static fn (int $v) => $v > 0));
            if ($ids !== []) {
                $in = implode(',', $ids);
                $query->whereRaw("(venue_id IN ({$in}) OR venue_id IS NULL)");
            }
        }
        if ($venueId > 0) {
            if (!$this->canAccessVenue($venueId)) {
                return json(['code' => 403, 'msg' => '无权查看该钓场订单', 'data' => null]);
            }
            $query->where('venue_id', $venueId);
        }
        if ($pondId > 0) {
            $query->where('pond_id', $pondId);
        }
        if ($orderNo !== '') {
            $query->where('order_no', $orderNo);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows      = $paginator->items();

        // 预加载关联数据，避免 N+1 查询
        $pondIds  = [];
        $venueIds = [];
        $userIds  = [];
        foreach ($rows as $row) {
            if ($row->pond_id) {
                $pondIds[] = (int) $row->pond_id;
            }
            if ($row->venue_id) {
                $venueIds[] = (int) $row->venue_id;
            }
            if ($row->mini_user_id) {
                $userIds[] = (int) $row->mini_user_id;
            }
        }
        $pondIds  = array_values(array_unique($pondIds));
        $venueIds = array_values(array_unique($venueIds));
        $userIds  = array_values(array_unique($userIds));

        $pondMap  = $pondIds ? FishingPond::whereIn('id', $pondIds)->column('name', 'id') : [];
        $venueMap = $venueIds ? FishingVenue::whereIn('id', $venueIds)->column('name', 'id') : [];
        $userMap  = $userIds ? MiniUser::whereIn('id', $userIds)->column('nickname', 'id') : [];

        $list = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $arr['amount_total_yuan'] = round(((int) $arr['amount_total']) / 100, 2);
            $arr['amount_paid_yuan']  = round(((int) $arr['amount_paid']) / 100, 2);
            $arr['venue_name']        = $arr['venue_id'] ? ($venueMap[$arr['venue_id']] ?? '') : '';
            $arr['pond_name']         = $arr['pond_id'] ? ($pondMap[$arr['pond_id']] ?? '') : '';
            $arr['user_nickname']     = $arr['mini_user_id'] ? ($userMap[$arr['mini_user_id']] ?? '') : '';
            $arr['order_source_label'] = self::orderSourceLabel((string) ($arr['description'] ?? ''));
            $list[] = $arr;
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $list,
                'total' => $paginator->total(),
            ],
        ]);
    }

    /** 列表展示用：根据 description 归纳订单业务来源 */
    private static function orderSourceLabel(string $description): string
    {
        $d = trim($description);
        if ($d === MemberBalanceRechargeService::ORDER_DESCRIPTION) {
            return '会员余额充值';
        }
        if ($d !== '' && mb_strpos($d, '开钓单预付款') !== false) {
            return '开钓单/开卡预付款';
        }
        if ($d !== '' && mb_strpos($d, '活动报名预付款') !== false) {
            return '活动报名预付款';
        }
        if ($d !== '' && mb_strpos($d, '店铺订单') !== false) {
            return '店铺订单';
        }

        return $d !== '' ? ('其它：' . $d) : '其它';
    }
}

