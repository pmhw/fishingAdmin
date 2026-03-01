<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 系统配置（Key-Value）
 * 表：system_config，字段 config_key / config_value / remark
 */
class SystemConfig extends Model
{
    protected $table = 'system_config';
}
