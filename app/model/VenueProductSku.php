<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 钓场店铺 - SKU 售价与库存
 */
class VenueProductSku extends Model
{
    protected $table = 'venue_product_sku';

    public function venueProduct()
    {
        return $this->belongsTo(VenueProduct::class, 'venue_product_id', 'id');
    }

    public function productSku()
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id', 'id');
    }
}
