<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 池塘放鱼记录
 */
class PondFeedLog extends Model
{
    protected $table = 'pond_feed_log';

    public function pond()
    {
        return $this->belongsTo(FishingPond::class, 'pond_id', 'id');
    }
}

