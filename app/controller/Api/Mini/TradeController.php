<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\PondReturnLog;
use app\model\VenueShopOrder;
use app\service\MemberBalanceRechargeService;
use think\facade\Db;
use think\response\Json;

/**
 * 小程序端：交易聚合列表（不新增表，从 fishing_order / venue_shop_order / pond_return_log 聚合）
 *
 * GET /api/mini/trades
 *
 * 过滤：不含支付超时钓场单、不含店铺超时关单、回鱼仅含打款成功（到账）记录。
 */
class TradeController extends MiniBaseController
{
    /** @var bool|null 是否已有 pond_return_log.payout_status（缓存，避免重复 SHOW COLUMNS） */
    private static ?bool $pondReturnHasPayoutStatusColumn = null;

    /**
     * Query：page，limit（默认 10，最大 50），kind：all | fishing | shop | return
     */
    public function list(): Json
    {
        [$user, $err] = $this->getCurrentUserOrFail();
        if ($err !== null) {
            return $err;
        }

        $uid = (int) $user->id;
        $page = max(1, (int) $this->request->get('page', 1));
        $limit = min(50, max(1, (int) $this->request->get('limit', 10)));
        $offset = ($page - 1) * $limit;
        $kind = trim((string) $this->request->get('kind', 'all'));
        if (!in_array($kind, ['all', 'fishing', 'shop', 'return'], true)) {
            $kind = 'all';
        }

        [$sqlUnion, $binds] = $this->buildUnionSql($uid, $kind);
        if ($sqlUnion === '') {
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => ['list' => [], 'total' => 0, 'page' => $page, 'limit' => $limit],
            ]);
        }

        $countRow = Db::query(
            'SELECT COUNT(1) AS c FROM (' . $sqlUnion . ') x',
            $binds
        );
        $total = isset($countRow[0]['c']) ? (int) $countRow[0]['c'] : 0;

        $rows = Db::query(
            'SELECT * FROM (' . $sqlUnion . ') t ORDER BY t.sort_ts DESC, t.ref_id DESC LIMIT ' . (int) $offset . ',' . (int) $limit,
            $binds
        );

        $list = $this->formatRows($rows);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $list,
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: array<int, int>}
     */
    private function buildUnionSql(int $uid, string $kind): array
    {
        $binds = [];
        $parts = [];

        $fishingSql = "
SELECT
  'fishing_order' AS item_type,
  fo.id AS ref_id,
  fo.order_no AS biz_no,
  IFNULL(fo.description, '') AS title_raw,
  fo.status AS status,
  fo.amount_total AS amt_fen,
  fo.amount_paid AS paid_fen,
  fo.created_at AS created_at,
  fo.pay_time AS pay_time,
  COALESCE(fo.pay_time, fo.created_at) AS sort_ts
FROM fishing_order fo
WHERE fo.mini_user_id = ?
  AND fo.status <> 'timeout'
  AND NOT (fo.order_no LIKE 'SO%' OR IFNULL(fo.description, '') LIKE '%店铺订单%')
";

        $shopSql = "
SELECT
  'shop_order' AS item_type,
  v.id AS ref_id,
  v.order_no AS biz_no,
  '店铺订单' AS title_raw,
  v.status AS status,
  v.amount_goods_fen AS amt_fen,
  CASE WHEN v.status = 'paid' THEN IFNULL(v.wx_amount_fen, 0) + IFNULL(v.balance_deduct_fen, 0) ELSE 0 END AS paid_fen,
  v.created_at AS created_at,
  v.pay_time AS pay_time,
  COALESCE(v.pay_time, v.created_at) AS sort_ts
FROM venue_shop_order v
WHERE v.mini_user_id = ?
  AND v.status <> 'closed'
";

        $returnPayoutClause = $this->pondReturnLogHasPayoutStatusColumn()
            ? 'AND prl.payout_status = \'success\''
            : 'AND 1 = 0';

        $returnSql = "
SELECT
  'return_log' AS item_type,
  prl.id AS ref_id,
  CONCAT('RL', prl.id) AS biz_no,
  IFNULL(NULLIF(TRIM(prl.remark), ''), '回鱼流水') AS title_raw,
  'record' AS status,
  ROUND(prl.amount * 100) AS amt_fen,
  ROUND(prl.amount * 100) AS paid_fen,
  prl.created_at AS created_at,
  prl.created_at AS pay_time,
  prl.created_at AS sort_ts
FROM pond_return_log prl
INNER JOIN fishing_session fs ON fs.id = prl.session_id
WHERE fs.mini_user_id = ?
  {$returnPayoutClause}
";

        if ($kind === 'all' || $kind === 'fishing') {
            $parts[] = '(' . $fishingSql . ')';
            $binds[] = $uid;
        }
        if ($kind === 'all' || $kind === 'shop') {
            $parts[] = '(' . $shopSql . ')';
            $binds[] = $uid;
        }
        if ($kind === 'all' || $kind === 'return') {
            $parts[] = '(' . $returnSql . ')';
            $binds[] = $uid;
        }

        if ($parts === []) {
            return ['', []];
        }

        return [implode(' UNION ALL ', $parts), $binds];
    }

    /**
     * 无 payout_status 字段时无法在 SQL 中区分是否到账，回鱼分支不返回任何行（需执行 md/pond_return_log_payout_fields.sql）
     */
    private function pondReturnLogHasPayoutStatusColumn(): bool
    {
        if (self::$pondReturnHasPayoutStatusColumn !== null) {
            return self::$pondReturnHasPayoutStatusColumn;
        }
        try {
            $rows = Db::query("SHOW COLUMNS FROM `pond_return_log` LIKE 'payout_status'");
            self::$pondReturnHasPayoutStatusColumn = !empty($rows);
        } catch (\Throwable $e) {
            self::$pondReturnHasPayoutStatusColumn = false;
        }

        return self::$pondReturnHasPayoutStatusColumn;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatRows(array $rows): array
    {
        $shopIds = [];
        $returnIds = [];
        foreach ($rows as $r) {
            $t = (string) ($r['item_type'] ?? '');
            if ($t === 'shop_order') {
                $shopIds[] = (int) ($r['ref_id'] ?? 0);
            }
            if ($t === 'return_log') {
                $returnIds[] = (int) ($r['ref_id'] ?? 0);
            }
        }

        $shopMap = [];
        if ($shopIds !== []) {
            $shopIds = array_values(array_unique(array_filter($shopIds)));
            foreach (VenueShopOrder::whereIn('id', $shopIds)->select() as $s) {
                $shopMap[(int) $s->id] = $s;
            }
        }

        $returnMap = [];
        if ($returnIds !== []) {
            $returnIds = array_values(array_unique(array_filter($returnIds)));
            foreach (PondReturnLog::whereIn('id', $returnIds)->select() as $l) {
                $returnMap[(int) $l->id] = $l;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $type = (string) ($r['item_type'] ?? '');
            if ($type === 'fishing_order') {
                $out[] = $this->formatFishingRow($r);
            } elseif ($type === 'shop_order') {
                $sid = (int) ($r['ref_id'] ?? 0);
                $out[] = $this->formatShopRow($r, $shopMap[$sid] ?? null);
            } elseif ($type === 'return_log') {
                $rid = (int) ($r['ref_id'] ?? 0);
                $out[] = $this->formatReturnRow($r, $returnMap[$rid] ?? null);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function formatFishingRow(array $r): array
    {
        $desc = (string) ($r['title_raw'] ?? '');
        $status = (string) ($r['status'] ?? '');
        $amtFen = (int) ($r['amt_fen'] ?? 0);
        $paidFen = (int) ($r['paid_fen'] ?? 0);
        $bizNo = (string) ($r['biz_no'] ?? '');

        $title = $desc !== '' ? $desc : '钓场订单';
        $sub = $this->fishingOrderSubtitle($desc);

        return [
            'item_type'     => 'fishing_order',
            'ref_id'        => (int) ($r['ref_id'] ?? 0),
            'biz_no'        => $bizNo,
            /** 与 GET /api/mini/orders/:order_no 一致，可用 biz_no 拉详情 */
            'order_no'      => $bizNo,
            'title'         => $title,
            'subtitle'      => $sub,
            'status'        => $status,
            'status_text'   => $this->fishingStatusText($status),
            'amount_yuan'   => (string) number_format($amtFen / 100, 2, '.', ''),
            'paid_yuan'     => (string) number_format($paidFen / 100, 2, '.', ''),
            'need_pay_yuan' => (string) number_format(max(0, $amtFen - $paidFen) / 100, 2, '.', ''),
            'sort_ts'       => (string) ($r['sort_ts'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $r
     */
    private function formatShopRow(array $r, ?VenueShopOrder $shop): array
    {
        $status = (string) ($r['status'] ?? '');
        $goodsFen = (int) ($r['amt_fen'] ?? 0);
        $balanceYuan = '0.00';
        $wxYuan = '0.00';
        if ($shop) {
            $balanceYuan = (string) number_format((int) ($shop->balance_deduct_fen ?? 0) / 100, 2, '.', '');
            $wxYuan = (string) number_format((int) ($shop->wx_amount_fen ?? 0) / 100, 2, '.', '');
        }

        $bizNo = (string) ($r['biz_no'] ?? '');

        return [
            'item_type'           => 'shop_order',
            'ref_id'              => (int) ($r['ref_id'] ?? 0),
            'biz_no'              => $bizNo,
            'order_no'            => $bizNo,
            'title'               => '店铺订单',
            'subtitle'            => '商品消费',
            'status'              => $status,
            'status_text'         => match ($status) {
                'paid'    => '已支付',
                'pending' => '待支付',
                'closed'  => '已关闭',
                default   => $status !== '' ? $status : '未知',
            },
            'amount_yuan'         => (string) number_format($goodsFen / 100, 2, '.', ''),
            'balance_deduct_yuan' => $balanceYuan,
            'wx_pay_yuan'         => $wxYuan,
            'sort_ts'             => (string) ($r['sort_ts'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $r
     */
    private function formatReturnRow(array $r, ?PondReturnLog $log): array
    {
        $title = (string) ($r['title_raw'] ?? '回鱼流水');
        $amt = $log ? (float) ($log->amount ?? 0) : ((int) ($r['amt_fen'] ?? 0)) / 100;

        $payoutStatus = 'none';
        $payoutChannel = null;
        if ($log) {
            $la = $log->toArray();
            $payoutStatus = (string) ($la['payout_status'] ?? 'none');
            $payoutChannel = isset($la['payout_channel']) && (string) $la['payout_channel'] !== ''
                ? (string) $la['payout_channel']
                : null;
        }

        $statusText = '已记录';
        if ($payoutStatus === 'success') {
            $statusText = $payoutChannel === 'balance' ? '已入余额' : ($payoutChannel === 'wechat' ? '已打款' : '已结算');
        } elseif ($payoutStatus === 'pending') {
            $statusText = '打款处理中';
        } elseif ($payoutStatus === 'failed') {
            $statusText = '打款失败';
        }

        return [
            'item_type'       => 'return_log',
            'ref_id'          => (int) ($r['ref_id'] ?? 0),
            'biz_no'          => (string) ($r['biz_no'] ?? ''),
            'title'           => $title,
            'subtitle'        => '回鱼流水',
            'status'          => $payoutStatus !== 'none' && $payoutStatus !== '' ? $payoutStatus : 'record',
            'status_text'     => $statusText,
            'amount_yuan'     => (string) number_format($amt, 2, '.', ''),
            'payout_channel'  => $payoutChannel,
            'sort_ts'         => (string) ($r['sort_ts'] ?? ''),
        ];
    }

    private function fishingStatusText(string $status): string
    {
        return match ($status) {
            'paid'    => '已支付',
            'pending' => '待支付',
            'timeout' => '支付超时',
            default   => $status !== '' ? $status : '未知',
        };
    }

    private function fishingOrderSubtitle(string $description): string
    {
        if ($description === MemberBalanceRechargeService::ORDER_DESCRIPTION) {
            return '会员充值';
        }
        if (str_contains($description, '开钓单')) {
            return '开钓单';
        }
        if (str_contains($description, '活动报名')) {
            return '活动报名';
        }

        return '钓场订单';
    }
}
