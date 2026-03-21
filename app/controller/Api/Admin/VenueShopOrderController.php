<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\MiniUser;
use app\model\VenueShopOrder;
use app\model\VenueShopOrderItem;
use think\response\Json;

/**
 * 交易中心 - 店铺商品订单（只读）
 */
class VenueShopOrderController extends BaseController
{
    use VenueScopeTrait;

    /**
     * GET /api/admin/shop/orders
     *
     * query: page, limit, status, venue_id, order_no
     */
    public function list(): Json
    {
        $page    = max((int) $this->request->get('page', 1), 1);
        $limit   = min(max((int) $this->request->get('limit', 10), 1), 100);
        $status  = trim((string) $this->request->get('status', ''));
        $venueId = (int) $this->request->get('venue_id', 0);
        $orderNo = trim((string) $this->request->get('order_no', ''));

        $allowed = $this->getAdminAllowedVenueIds();
        if (is_array($allowed) && $allowed === []) {
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => ['list' => [], 'total' => 0],
            ]);
        }

        $query = VenueShopOrder::order('id', 'desc');
        if ($allowed !== null) {
            $query->whereIn('venue_id', $allowed);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($venueId > 0) {
            if (!$this->canAccessVenue($venueId)) {
                return json(['code' => 403, 'msg' => '无权查看该钓场订单', 'data' => null]);
            }
            $query->where('venue_id', $venueId);
        }
        if ($orderNo !== '') {
            $query->where('order_no', $orderNo);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows      = $paginator->items();

        $venueIds = [];
        $userIds  = [];
        $pondIds  = [];
        foreach ($rows as $row) {
            $venueIds[] = (int) $row->venue_id;
            $userIds[]  = (int) $row->mini_user_id;
            if (!empty($row->pond_id)) {
                $pondIds[] = (int) $row->pond_id;
            }
        }
        $venueIds = array_values(array_unique(array_filter($venueIds)));
        $userIds  = array_values(array_unique(array_filter($userIds)));
        $pondIds  = array_values(array_unique(array_filter($pondIds)));

        $venueMap = $venueIds ? FishingVenue::whereIn('id', $venueIds)->column('name', 'id') : [];
        $userMap  = $userIds ? MiniUser::whereIn('id', $userIds)->column('nickname', 'id') : [];
        $pondMap  = $pondIds ? FishingPond::whereIn('id', $pondIds)->column('name', 'id') : [];

        $list = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $arr['amount_goods_yuan']     = round(((int) ($arr['amount_goods_fen'] ?? 0)) / 100, 2);
            $arr['balance_deduct_yuan']   = round(((int) ($arr['balance_deduct_fen'] ?? 0)) / 100, 2);
            $arr['wx_amount_yuan']        = round(((int) ($arr['wx_amount_fen'] ?? 0)) / 100, 2);
            $arr['venue_name']            = $venueMap[(int) ($arr['venue_id'] ?? 0)] ?? '';
            $arr['user_nickname']         = $userMap[(int) ($arr['mini_user_id'] ?? 0)] ?? '';
            $pid = (int) ($arr['pond_id'] ?? 0);
            $arr['pond_name']             = $pid > 0 ? ($pondMap[$pid] ?? '') : '';
            $arr['seat_display']          = $this->formatSeatDisplay($arr);
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

    /**
     * GET /api/admin/shop/orders/:id
     * id 为 venue_shop_order 主键
     */
    public function detail(int $id): Json
    {
        /** @var VenueShopOrder|null $order */
        $order = VenueShopOrder::with(['items'])->find($id);
        if (!$order) {
            return json(['code' => 404, 'msg' => '订单不存在', 'data' => null]);
        }
        if (!$this->canAccessVenue((int) $order->venue_id)) {
            return json(['code' => 403, 'msg' => '无权查看该订单', 'data' => null]);
        }

        $arr = $order->toArray();
        $arr['amount_goods_yuan']   = round(((int) ($arr['amount_goods_fen'] ?? 0)) / 100, 2);
        $arr['balance_deduct_yuan'] = round(((int) ($arr['balance_deduct_fen'] ?? 0)) / 100, 2);
        $arr['wx_amount_yuan']      = round(((int) ($arr['wx_amount_fen'] ?? 0)) / 100, 2);
        $v = FishingVenue::find((int) $order->venue_id);
        $arr['venue_name'] = $v ? (string) ($v->name ?? '') : '';
        $u = MiniUser::find((int) $order->mini_user_id);
        $arr['user_nickname'] = $u ? (string) ($u->nickname ?? '') : '';
        $pid = (int) ($order->pond_id ?? 0);
        if ($pid > 0) {
            $p = FishingPond::find($pid);
            $arr['pond_name'] = $p ? (string) ($p->name ?? '') : '';
        } else {
            $arr['pond_name'] = '';
        }
        $arr['seat_display'] = $this->formatSeatDisplay($arr);
        $sid = (int) ($order->fishing_session_id ?? 0);
        if ($sid > 0) {
            $sess = FishingSession::find($sid);
            $arr['session_no'] = $sess ? (string) ($sess->session_no ?? '') : '';
        } else {
            $arr['session_no'] = '';
        }

        $itemRows = [];
        foreach ($order->items ?? [] as $it) {
            /** @var VenueShopOrderItem $it */
            $itemRows[] = [
                'id'                 => (int) $it->id,
                'product_name'       => (string) ($it->product_name ?? ''),
                'spec_label'         => (string) ($it->spec_label ?? ''),
                'price_yuan'         => round(((int) ($it->price_fen ?? 0)) / 100, 2),
                'quantity'           => (int) ($it->quantity ?? 0),
                'line_total_yuan'    => round(((int) ($it->line_total_fen ?? 0)) / 100, 2),
                'venue_product_id'   => (int) ($it->venue_product_id ?? 0),
                'venue_product_sku_id' => (int) ($it->venue_product_sku_id ?? 0),
            ];
        }
        $arr['items'] = $itemRows;

        return json(['code' => 0, 'msg' => 'success', 'data' => $arr]);
    }

    /**
     * @param array<string, mixed> $arr
     */
    private function formatSeatDisplay(array $arr): string
    {
        $code = trim((string) ($arr['seat_code'] ?? ''));
        if ($code !== '') {
            return $code;
        }
        $no = isset($arr['seat_no']) ? (int) $arr['seat_no'] : 0;

        return $no > 0 ? '钓位 #' . $no : '—';
    }
}
