<?php
declare(strict_types = 1);

namespace app\service;

use app\model\ActivityParticipation;
use app\model\FishingOrder;
use app\model\MiniUser;
use app\model\VenueProductSku;
use app\model\VenueShopOrder;
use app\model\VenueShopOrderItem;
use think\facade\Db;

/**
 * 待支付订单超时：超过 N 分钟仍为 pending 则标记为 timeout，并做关联善后（释放活动报名占位、店铺单回滚库存与余额）
 */
class OrderTimeoutService
{
    /**
     * @return int 实际处理条数
     */
    public static function run(int $limit = 200, ?int $timeoutMinutes = null): int
    {
        if ($timeoutMinutes === null) {
            $timeoutMinutes = max(1, (int) (config('wechat_pay.order_pending_timeout_minutes') ?? 5));
        }
        $threshold = date('Y-m-d H:i:s', time() - $timeoutMinutes * 60);

        $orders = FishingOrder::where('status', 'pending')
            ->where('created_at', '<=', $threshold)
            ->order('id', 'asc')
            ->limit($limit)
            ->select();

        $done = 0;
        foreach ($orders as $order) {
            try {
                Db::transaction(function () use ($order) {
                    /** @var FishingOrder|null $fresh */
                    $fresh = FishingOrder::where('id', (int) $order->id)
                        ->where('status', 'pending')
                        ->lock(true)
                        ->find();
                    if (!$fresh) {
                        return;
                    }
                    self::applySideEffectsBeforeTimeout($fresh);
                    $fresh->status = 'timeout';
                    $fresh->save();
                });
                $done++;
            } catch (\Throwable $e) {
                // 单条失败跳过
            }
        }

        return $done;
    }

    private static function applySideEffectsBeforeTimeout(FishingOrder $order): void
    {
        $orderNo = (string) ($order->order_no ?? '');
        $desc = (string) ($order->description ?? '');

        if ($orderNo === '') {
            return;
        }

        // 店铺订单：同号 SO* + fishing_order 占位
        if (str_starts_with($orderNo, 'SO') || str_contains($desc, '店铺订单')) {
            self::timeoutShopOrder($orderNo);
            return;
        }

        // 活动报名：退回已扣会员余额，删除待支付参与记录，允许用户重新报名
        if (str_contains($desc, '活动报名预付款')) {
            /** @var ActivityParticipation|null $part */
            $part = ActivityParticipation::where('pay_order_no', $orderNo)
                ->where('pay_status', 'pending')
                ->find();
            if ($part) {
                $deductFen = (int) ($part->balance_deduct_fen ?? 0);
                if ($deductFen > 0) {
                    $u = MiniUser::find((int) $part->mini_user_id);
                    if ($u) {
                        $u->balance = ((float) ($u->balance ?? 0)) + $deductFen / 100;
                        $u->save();
                    }
                }
                $part->delete();
            }
        }
    }

    private static function timeoutShopOrder(string $orderNo): void
    {
        /** @var VenueShopOrder|null $shop */
        $shop = VenueShopOrder::where('order_no', $orderNo)->find();
        if (!$shop || (string) ($shop->status ?? '') !== 'pending') {
            return;
        }

        $items = VenueShopOrderItem::where('shop_order_id', (int) $shop->id)->select();
        foreach ($items as $it) {
            $vpsId = (int) ($it->venue_product_sku_id ?? 0);
            if ($vpsId < 1) {
                continue;
            }
            $vps = VenueProductSku::find($vpsId);
            if ($vps) {
                $vps->stock = (int) ($vps->stock ?? 0) + (int) ($it->quantity ?? 0);
                $vps->save();
            }
        }

        $deductFen = (int) ($shop->balance_deduct_fen ?? 0);
        if ($deductFen > 0) {
            $u = MiniUser::find((int) $shop->mini_user_id);
            if ($u) {
                $u->balance = ((float) ($u->balance ?? 0)) + $deductFen / 100;
                $u->save();
            }
        }

        $shop->status = 'closed';
        $shop->save();
    }
}
