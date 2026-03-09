<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 小程序订单（钓场/池塘/钓位）
 */
class FishingOrder extends Model
{
    protected $table = 'fishing_order';

    public function user()
    {
        return $this->belongsTo(MiniUser::class, 'mini_user_id', 'id');
    }

    public function pond()
    {
        return $this->belongsTo(FishingPond::class, 'pond_id', 'id');
    }

    public function seat()
    {
        return $this->belongsTo(PondSeat::class, 'seat_id', 'id');
    }
}

