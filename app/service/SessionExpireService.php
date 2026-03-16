<?php
declare (strict_types = 1);

namespace app\service;

use app\model\FishingSession;
use think\facade\Cache;

/**
 * 到期自动结束（方案A）服务：支持命令/请求兜底复用
 */
class SessionExpireService
{
    private const CACHE_KEY = 'session_expire:last_run_ts';

    /**
     * 在请求链路中兜底执行：带节流，避免每次请求都扫库
     */
    public static function tick(int $cooldownSeconds = 30, int $limit = 50): void
    {
        try {
            $nowTs = time();
            $last = (int) Cache::get(self::CACHE_KEY, 0);
            if ($last > 0 && ($nowTs - $last) < $cooldownSeconds) {
                return;
            }
            Cache::set(self::CACHE_KEY, $nowTs, $cooldownSeconds);
            self::run($limit);
        } catch (\Throwable $e) {
            // 请求兜底：忽略异常，避免影响主流程
        }
    }

    /**
     * 执行到期结束
     * @return int 实际结束数量
     */
    public static function run(int $limit = 200): int
    {
        $now = date('Y-m-d H:i:s');
        $rows = FishingSession::where('status', 'ongoing')
            ->whereNotNull('expire_time')
            ->where('expire_time', '<=', $now)
            ->order('id', 'asc')
            ->limit($limit)
            ->select();

        $count = 0;
        foreach ($rows as $row) {
            // 幂等保护：再次确认 ongoing
            if ((string) $row->status !== 'ongoing') {
                continue;
            }
            $row->status = 'finished';
            $row->end_time = $now;
            $row->save();
            $count++;
        }
        return $count;
    }
}

