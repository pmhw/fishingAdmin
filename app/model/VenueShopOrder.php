<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 钓场店铺订单（主表）
 */
class VenueShopOrder extends Model
{
    protected $table = 'venue_shop_order';

    public function items()
    {
        return $this->hasMany(VenueShopOrderItem::class, 'shop_order_id', 'id');
    }

    public function venue()
    {
        return $this->belongsTo(FishingVenue::class, 'venue_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(MiniUser::class, 'mini_user_id', 'id');
    }
}
