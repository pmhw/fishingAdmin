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
 * 返回当前可见范围内 fishing_order / venue_shop_order 的最大 id（无单则为 0）
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

        if ($allowed !== null) {
            $shopMax = (int) (VenueShopOrder::whereIn('venue_id', $allowed)->max('id') ?? 0);
            $fishMax = (int) (FishingOrder::whereIn('venue_id', $allowed)->max('id') ?? 0);
        } else {
            $shopMax = (int) (VenueShopOrder::max('id') ?? 0);
            $fishMax = (int) (FishingOrder::max('id') ?? 0);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'fishing_order_max_id' => $fishMax,
                'shop_order_max_id'    => $shopMax,
            ],
        ]);
    }
}
