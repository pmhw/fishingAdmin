<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 池塘回鱼规则（条数范围 + 按斤/按条）
 */
class PondReturnRule extends Model
{
    protected $table = 'pond_return_rule';

    public function pond()
    {
        return $this->belongsTo(FishingPond::class, 'pond_id', 'id');
    }
}
