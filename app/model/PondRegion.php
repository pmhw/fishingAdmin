<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 钓位区域（归属池塘），如 西岸1~29、中间浮桥30~89
 */
class PondRegion extends Model
{
    protected $table = 'pond_region';

    public function pond()
    {
        return $this->belongsTo(FishingPond::class, 'pond_id', 'id');
    }
}
