<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 钓场店铺 - 已上架 SPU
 */
class VenueProduct extends Model
{
    protected $table = 'venue_product';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function venue()
    {
        return $this->belongsTo(FishingVenue::class, 'venue_id', 'id');
    }

    public function shopCategory()
    {
        return $this->belongsTo(VenueShopCategory::class, 'shop_category_id', 'id');
    }

    public function venueSkus()
    {
        return $this->hasMany(VenueProductSku::class, 'venue_product_id', 'id');
    }
}
