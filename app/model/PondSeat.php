<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 钓位（独立座位号 + 唯一 code），用于扫码/点餐/计费/回鱼等业务关联
 */
class PondSeat extends Model
{
    protected $table = 'pond_seat';

    public function pond()
    {
        return $this->belongsTo(FishingPond::class, 'pond_id', 'id');
    }

    public function region()
    {
        return $this->belongsTo(PondRegion::class, 'region_id', 'id');
    }
}

