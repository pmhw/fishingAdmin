<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 池塘收费规则
 */
class PondFeeRule extends Model
{
    protected $table = 'pond_fee_rule';

    public function pond()
    {
        return $this->belongsTo(FishingPond::class, 'pond_id', 'id');
    }
}
