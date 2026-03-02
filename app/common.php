<?php
// 应用公共文件

/**
 * 获取全局配置项（system_config 表）
 * @param string $key 变量名
 * @param mixed $default 默认值
 * @return string|mixed
 */
function get_config(string $key, $default = '')
{
    return \app\model\SystemConfig::getValue($key, $default);
}
