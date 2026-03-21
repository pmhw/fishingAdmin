<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 公共商品库 SPU
 */
class Product extends Model
{
    protected $table = 'product';

    public function skus()
    {
        return $this->hasMany(ProductSku::class, 'product_id', 'id')->order('sort_order', 'asc')->order('id', 'asc');
    }
}
