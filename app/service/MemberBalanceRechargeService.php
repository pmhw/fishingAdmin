<?php
declare(strict_types=1);

namespace app\service;

use app\model\FishingOrder;
use app\model\MiniUser;
use app\model\SystemConfig;

/**
 * 会员余额充值（微信支付 fishing_order）与「单笔实付满额升级 VIP」
 *
 * 订单约定：description 固定为 {@see MemberBalanceRechargeService::ORDER_DESCRIPTION}
 * 配置：system_config balance_recharge_packages（JSON 数组，单位元）、vip_upgrade_recharge_threshold_yuan（元，0 表示不自动升级）
 */
class MemberBalanceRechargeService
{
    public const ORDER_DESCRIPTION = '会员余额充值';

    public const CONFIG_PACKAGES = 'balance_recharge_packages';

    public const CONFIG_VIP_THRESHOLD_YUAN = 'vip_upgrade_recharge_threshold_yuan';

    public static function isBalanceRechargeOrder(FishingOrder $order): bool
    {
        return (string) ($order->description ?? '') === self::ORDER_DESCRIPTION;
    }

    /**
     * 小程序展示用：可选档位（元）、升级门槛（元）
     *
     * @return array{packages_yuan: float[], vip_upgrade_threshold_yuan: float, vip_upgrade_enabled: bool}
     */
    public static function getOptionsForMini(): array
    {
        $packages = self::parsePackagesYuan();
        $threshold = self::getThresholdYuan();

        return [
            'packages_yuan'               => $packages,
            'vip_upgrade_threshold_yuan'  => $threshold,
            'vip_upgrade_enabled'         => $threshold > 0,
        ];
    }

    /**
     * @return float[]
     */
    public static function parsePackagesYuan(): array
    {
        $raw = trim((string) SystemConfig::getValue(self::CONFIG_PACKAGES, ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $v) {
            if (is_int($v) || is_float($v)) {
                $y = (float) $v;
            } elseif (is_string($v) && is_numeric(trim($v))) {
                $y = (float) trim($v);
            } else {
                continue;
            }
            if ($y <= 0 || $y > 1_000_000) {
                continue;
            }
            $out[] = round($y, 2);
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_NUMERIC);

        return $out;
    }

    public static function getThresholdYuan(): float
    {
        $raw = trim((string) SystemConfig::getValue(self::CONFIG_VIP_THRESHOLD_YUAN, '0'));

        return is_numeric($raw) ? max(0.0, round((float) $raw, 2)) : 0.0;
    }

    /**
     * @return int[] 允许充值的金额（分），升序唯一
     */
    public static function allowedAmountsFen(): array
    {
        $fenList = [];
        foreach (self::parsePackagesYuan() as $yuan) {
            $fenList[] = (int) round($yuan * 100);
        }
        $fenList = array_values(array_unique(array_filter($fenList, static fn (int $f) => $f > 0)));
        sort($fenList, SORT_NUMERIC);

        return $fenList;
    }

    /**
     * 创建待支付充值订单（金额必须在后台配置的档位中）
     *
     * @throws \InvalidArgumentException
     */
    public static function createOrder(MiniUser $user, float $amountYuan): FishingOrder
    {
        $allowed = self::allowedAmountsFen();
        if ($allowed === []) {
            throw new \InvalidArgumentException('暂未开放余额充值');
        }
        $fen = (int) round($amountYuan * 100);
        if (!in_array($fen, $allowed, true)) {
            throw new \InvalidArgumentException('充值金额不在可选档位');
        }

        $orderNo = date('YmdHis') . sprintf('%04d', (int) $user->id) . random_int(1000, 9999);

        return FishingOrder::create([
            'order_no'       => $orderNo,
            'mini_user_id'   => (int) $user->id,
            'venue_id'       => null,
            'pond_id'        => null,
            'seat_id'        => null,
            'seat_no'        => null,
            'seat_code'      => null,
            'fee_rule_id'    => null,
            'return_rule_id' => null,
            'description'    => self::ORDER_DESCRIPTION,
            'amount_total'   => $fen,
            'amount_paid'    => 0,
            'status'         => 'pending',
            'pay_channel'    => 'wx_mini',
        ]);
    }

    /**
     * 支付成功回调内调用：已加行锁的订单、同一事务内给用户加余额并按门槛升 VIP
     */
    public static function creditBalanceAndMaybeVip(FishingOrder $order): void
    {
        if (!self::isBalanceRechargeOrder($order)) {
            return;
        }
        $uid = (int) ($order->mini_user_id ?? 0);
        if ($uid < 1) {
            return;
        }
        $paidFen = (int) ($order->amount_paid ?? 0);
        if ($paidFen < 1) {
            return;
        }

        /** @var MiniUser|null $user */
        $user = MiniUser::where('id', $uid)->lock(true)->find();
        if (!$user) {
            return;
        }

        $addYuan = round($paidFen / 100, 2);
        $balance = round((float) ($user->balance ?? 0) + $addYuan, 2);
        $user->balance = $balance;

        $thresholdYuan = self::getThresholdYuan();
        if ($thresholdYuan > 0) {
            $thresholdFen = (int) round($thresholdYuan * 100);
            if ($paidFen >= $thresholdFen) {
                // 已是会员时仍为 1，不会降级
                $user->is_vip = 1;
            }
        }

        $user->save();
    }

    /**
     * 后台读写：序列化后的配置值
     *
     * @return array{packages_yuan: float[], vip_upgrade_threshold_yuan: float}
     */
    public static function getAdminPayload(): array
    {
        return [
            'packages_yuan'               => self::parsePackagesYuan(),
            'vip_upgrade_threshold_yuan'  => self::getThresholdYuan(),
        ];
    }

    /**
     * @param float[] $packagesYuan
     *
     * @throws \InvalidArgumentException
     */
    public static function saveAdminPayload(array $packagesYuan, float $thresholdYuan): void
    {
        if ($thresholdYuan < 0 || $thresholdYuan > 1_000_000) {
            throw new \InvalidArgumentException('升级门槛金额不合法');
        }
        if (count($packagesYuan) > 30) {
            throw new \InvalidArgumentException('充值档位过多');
        }
        $normalized = [];
        foreach ($packagesYuan as $y) {
            $y = round((float) $y, 2);
            if ($y <= 0 || $y > 1_000_000) {
                throw new \InvalidArgumentException('档位金额须为正数且不超过 1000000');
            }
            $normalized[] = $y;
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized, SORT_NUMERIC);

        $packagesJson = json_encode($normalized, JSON_UNESCAPED_UNICODE);
        if ($packagesJson === false) {
            throw new \InvalidArgumentException('档位序列化失败');
        }

        self::upsertConfig(self::CONFIG_PACKAGES, $packagesJson, '会员余额充值可选档位（元），JSON 数组');
        self::upsertConfig(
            self::CONFIG_VIP_THRESHOLD_YUAN,
            (string) round($thresholdYuan, 2),
            '单笔充值实付满该金额（元）自动 is_vip=1，0 表示关闭'
        );
        SystemConfig::clearValueCache();
    }

    private static function upsertConfig(string $key, string $value, string $remark): void
    {
        $row = SystemConfig::where('config_key', $key)->find();
        if ($row) {
            $row->save(['config_value' => $value]);
        } else {
            SystemConfig::create([
                'config_key'   => $key,
                'config_value' => $value,
                'remark'       => $remark,
            ]);
        }
    }
}
