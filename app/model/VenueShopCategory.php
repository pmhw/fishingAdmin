<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 钓场店铺自定义分类（仅本店）
 */
class VenueShopCategory extends Model
{
    protected $table = 'venue_shop_category';

    public function venue()
    {
        return $this->belongsTo(FishingVenue::class, 'venue_id', 'id');
    }
}
