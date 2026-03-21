<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商品规格 SKU
 */
class ProductSku extends Model
{
    protected $table = 'product_sku';

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
