<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 小程序用户
 * 接口返回时自动隐藏敏感字段（见 $hidden）
 */
class MiniUser extends Model
{
    protected $table = 'mini_user';

    /** 接口返回 toArray() 时隐藏，避免泄露给前端 */
    protected $hidden = [
        'openid',
        'unionid',
        'mobile',
        'last_login_ip',
    ];
}

