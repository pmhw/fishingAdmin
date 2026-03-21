<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 钓场店铺订单明细
 */
class VenueShopOrderItem extends Model
{
    protected $table = 'venue_shop_order_item';

    public function shopOrder()
    {
        return $this->belongsTo(VenueShopOrder::class, 'shop_order_id', 'id');
    }
}
