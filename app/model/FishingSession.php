<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 开钓单（一次垂钓会话）
 */
class FishingSession extends Model
{
    protected $table = 'fishing_session';
}

