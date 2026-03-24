<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingOrder;
use app\model\VenueShopOrder;
use think\response\Json;

/**
 * 交易中心 - 新订单轮询（供后台弹窗/声音提醒，轻量）
 *
 * GET /api/admin/trade/order-alert-tip
 * 返回当前可见范围内「已支付」fishing_order / venue_shop_order 的最大 id（无则 0）。
 * 仅支付成功（status=paid）才计入，待支付下单不会触发提醒。
 */
class TradeAlertController extends BaseController
{
    use VenueScopeTrait;

    public function tip(): Json
    {
        $allowed = $this->getAdminAllowedVenueIds();

        if (is_array($allowed) && $allowed === []) {
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'fishing_order_max_id' => 0,
                    'shop_order_max_id'    => 0,
                ],
            ]);
        }

        $fishMax = $this->fishingPaidMaxId($allowed);
        $shopMax = $this->shopPaidMaxId($allowed);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'fishing_order_max_id' => $fishMax,
                'shop_order_max_id'    => $shopMax,
            ],
        ]);
    }

    /**
     * 非店铺 fishing_order：仅已支付，排除 SO 店铺占位单；有钓场范围时含 venue_id 为空的单（如会员充值）
     *
     * @param list<int>|null $allowed
     */
    private function fishingPaidMaxId(?array $allowed): int
    {
        $q = FishingOrder::where('status', 'paid')->where('order_no', 'not like', 'SO%');
        if ($allowed !== null) {
            $ids = array_values(array_unique(array_map('intval', $allowed)));
            $ids = array_values(array_filter($ids, static fn (int $v) => $v > 0));
            if ($ids === []) {
                return 0;
            }
            $in = implode(',', $ids);
            $q->whereRaw("(venue_id IN ({$in}) OR venue_id IS NULL)");
        }

        return (int) ($q->max('id') ?? 0);
    }

    /**
     * @param list<int>|null $allowed
     */
    private function shopPaidMaxId(?array $allowed): int
    {
        $q = VenueShopOrder::where('status', 'paid');
        if ($allowed !== null) {
            $ids = array_values(array_unique(array_map('intval', $allowed)));
            $ids = array_values(array_filter($ids, static fn (int $v) => $v > 0));
            if ($ids === []) {
                return 0;
            }
            $q->whereIn('venue_id', $ids);
        }

        return (int) ($q->max('id') ?? 0);
    }
}
