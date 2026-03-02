<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 系统配置（Key-Value）
 * 表：system_config，字段 config_key / config_value / remark
 * 全局获取：SystemConfig::getValue('key') 或 get_config('key')
 */
class SystemConfig extends Model
{
    protected $table = 'system_config';

    /** 内存缓存，避免同请求内重复查库 */
    private static array $valueCache = [];

    /**
     * 按 key 获取配置值，全局可调用（如接口、服务中）
     * @param string $key 变量名
     * @param mixed $default 不存在时的默认值
     * @return string|mixed
     */
    public static function getValue(string $key, $default = '')
    {
        if ($key === '') {
            return $default;
        }
        if (array_key_exists($key, self::$valueCache)) {
            return self::$valueCache[$key];
        }
        $row = self::where('config_key', $key)->find();
        $val = $row !== null ? (string) $row->config_value : $default;
        self::$valueCache[$key] = $val;
        return $val;
    }

    /** 清除内存缓存（如更新配置后希望下次 getValue 重新读库时调用） */
    public static function clearValueCache(): void
    {
        self::$valueCache = [];
    }
}
