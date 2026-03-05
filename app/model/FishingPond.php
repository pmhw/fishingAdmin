<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 池塘（归属钓场）
 */
class FishingPond extends Model
{
    protected $table = 'fishing_pond';

    /** 池塘类型 */
    public const TYPE_BLACK_PIT = 'black_pit';
    public const TYPE_JIN_TANG  = 'jin_tang';
    public const TYPE_PRACTICE  = 'practice';

    /** 池塘状态 */
    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    public function venue()
    {
        return $this->belongsTo(FishingVenue::class, 'venue_id', 'id');
    }
}
