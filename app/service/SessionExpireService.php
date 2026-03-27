<?php
declare (strict_types = 1);

namespace app\service;

use app\model\Activity;
use app\model\ActivityParticipation;
use app\model\FishingSession;
use app\model\PondFeeRule;
use think\facade\Cache;

/**
 * 到期自动超时服务：支持命令/请求兜底复用
 * 说明：超时仅置为 timeout，不自动结束；需管理员手动结束。
 */
class SessionExpireService
{
    private const CACHE_KEY = 'session_expire:last_run_ts';

    /**
     * 在请求链路中兜底执行：带节流，避免每次请求都扫库
     */
    public static function tick(int $cooldownSeconds = 30, int $limit = 150): void
    {
        try {
            $nowTs = time();
            $last = (int) Cache::get(self::CACHE_KEY, 0);
            if ($last > 0 && ($nowTs - $last) < $cooldownSeconds) {
                return;
            }
            Cache::set(self::CACHE_KEY, $nowTs, $cooldownSeconds);
            self::run($limit);
            OrderTimeoutService::run($limit);
        } catch (\Throwable $e) {
            // 请求兜底：忽略异常，避免影响主流程
        }
    }

    /**
     * 执行到期超时标记
     * @return int 实际标记数量
     */
    public static function run(int $limit = 200): int
    {
        $now = date('Y-m-d H:i:s');

        // 先修复：对进行中但未写 expire_time 的开钓单，尝试按收费规则回填 expire_time
        // 这样定时任务才能对历史/异常数据生效
        $needFill = FishingSession::where('status', 'ongoing')
            ->where(function ($q) {
                // 兼容：NULL / 空字符串 / 0000-00-00 00:00:00
                $q->whereNull('expire_time')
                  ->whereOr('expire_time', '=', '')
                  ->whereOr('expire_time', '=', '0000-00-00 00:00:00');
            })
            ->whereNotNull('fee_rule_id')
            ->where('fee_rule_id', '>', 0)
            ->order('id', 'asc')
            ->limit($limit)
            ->select();
        foreach ($needFill as $s) {
            try {
                $feeRuleId = (int) ($s->fee_rule_id ?? 0);
                if ($feeRuleId < 1) {
                    continue;
                }
                /** @var PondFeeRule|null $fee */
                $fee = PondFeeRule::find($feeRuleId);
                if (!$fee) {
                    continue;
                }
                $expireAt = self::backfillExpireTimeForSession($s, $fee);
                if ($expireAt !== null) {
                    $s->expire_time = $expireAt;
                    $s->save();
                }
            } catch (\Throwable $e) {
                // 忽略单条异常
            }
        }

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
            $row->status = 'timeout';
            // 兼容历史库：若已加 timeout_time 字段则写入；未加字段不阻塞主流程
            try {
                if ($row->hasField('timeout_time')) {
                    $row->timeout_time = $now;
                }
            } catch (\Throwable $e) {
            }
            $row->save();
            $count++;
        }
        return $count;
    }

    /**
     * 回填 expire_time：普通开钓单按 start_time + 规则时长；活动占座单无时长时走活动开钓时间 + 默认 24h
     */
    private static function backfillExpireTimeForSession(FishingSession $s, PondFeeRule $fee): ?string
    {
        $startTs = strtotime((string) ($s->start_time ?? ''));
        if ($startTs <= 0) {
            return null;
        }
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                return date('Y-m-d H:i:s', $startTs + $seconds);
            }
        }

        /** @var ActivityParticipation|null $part */
        $part = ActivityParticipation::where('assigned_session_id', (int) $s->id)->find();
        $activityId = 0;
        if ($part) {
            $activityId = (int) ($part->activity_id ?? 0);
        }
        if ($activityId < 1) {
            $activityId = (int) ($fee->activity_id ?? 0);
        }
        if ($activityId < 1) {
            return null;
        }
        /** @var Activity|null $activity */
        $activity = Activity::find($activityId);
        if (!$activity) {
            return null;
        }

        return ActivityPayService::computeActivitySessionExpireAt($activity, $fee);
    }
}

