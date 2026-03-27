<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingSession;
use app\model\PondReturnLog;
use app\model\SystemConfig;
use think\response\Json;

/**
 * 小程序端：回鱼打款（用户确认收款模式）
 * 说明：后台先发起打款，微信返回 package_info；小程序用该 package 拉起确认页。
 */
class ReturnPayoutController extends MiniBaseController
{
    /**
     * GET /api/mini/return-payouts
     * 需登录；仅返回当前用户自己的回鱼打款记录
     *
     * 可选 query：
     * - page, limit
     * - payout_status (none/pending/success/failed/cancelled)
     * - payout_channel (balance/wechat)
     */
    public function list(): Json
    {
        [$user, $err] = $this->getCurrentUserOrFail();
        if ($err !== null) {
            return $err;
        }

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $payoutStatus = trim((string) $this->request->get('payout_status', ''));
        $payoutChannel = trim((string) $this->request->get('payout_channel', ''));

        $sessionIds = FishingSession::where('mini_user_id', (int) $user->id)->column('id');
        $sessionIds = array_values(array_unique(array_map('intval', is_array($sessionIds) ? $sessionIds : [])));
        if ($sessionIds === []) {
            return json([
                'code' => 0,
                'msg'  => 'success',
                'data' => ['list' => [], 'total' => 0],
            ]);
        }

        $q = PondReturnLog::whereIn('session_id', $sessionIds)->order('id', 'desc');
        if ($payoutStatus !== '') {
            $q->where('payout_status', $payoutStatus);
        }
        if ($payoutChannel !== '') {
            $q->where('payout_channel', $payoutChannel);
        }

        $p = $q->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows = $p->items();
        $list = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $arr['can_confirm'] = ((string) ($arr['payout_status'] ?? '') === 'pending' && (string) ($arr['payout_channel'] ?? '') === 'wechat') ? 1 : 0;
            $list[] = $arr;
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list' => $list,
                'total' => $p->total(),
            ],
        ]);
    }

    /**
     * GET /api/mini/return-payouts/:id/package
     * 需登录；仅能获取自己的回鱼流水
     *
     * 返回：{ mchId, appId, package, openId? }
     */
    public function package(int $id): Json
    {
        [$user, $err] = $this->getCurrentUserOrFail();
        if ($err !== null) {
            return $err;
        }

        /** @var PondReturnLog|null $log */
        $log = PondReturnLog::find($id);
        if (!$log) {
            return json(['code' => 404, 'msg' => '记录不存在', 'data' => null]);
        }

        /** @var FishingSession|null $session */
        $session = FishingSession::find((int) ($log->session_id ?? 0));
        if (!$session || (int) ($session->mini_user_id ?? 0) !== (int) $user->id) {
            return json(['code' => 403, 'msg' => '无权访问', 'data' => null]);
        }

        if ((string) ($log->payout_channel ?? '') !== 'wechat') {
            return json(['code' => 400, 'msg' => '该记录不是微信打款', 'data' => null]);
        }
        if ((string) ($log->payout_status ?? '') !== 'pending') {
            return json(['code' => 400, 'msg' => '该记录未处于待确认状态', 'data' => null]);
        }

        $raw = (string) ($log->payout_raw ?? '');
        $pkg = '';
        if ($raw !== '') {
            $arr = json_decode($raw, true);
            // payout_raw 存的是 {http_code, body:{...}} 结构
            if (is_array($arr)) {
                $body = $arr['body'] ?? null;
                if (is_array($body)) {
                    $pkg = (string) ($body['package_info'] ?? $body['package'] ?? '');
                }
            }
        }
        if ($pkg === '') {
            return json(['code' => 400, 'msg' => '缺少 package_info，请联系管理员重新发起打款', 'data' => null]);
        }

        $mchId = (string) SystemConfig::getValue('pay_mch_id', '');
        $appId = (string) SystemConfig::getValue('wxpay_v3_appid', '');
        if ($mchId === '' || $appId === '') {
            return json(['code' => 500, 'msg' => '微信转账未配置（pay_mch_id/wxpay_v3_appid）', 'data' => null]);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'mchId'   => $mchId,
                'appId'   => $appId,
                'package' => $pkg,
                'openId'  => (string) ($user->openid ?? ''),
            ],
        ]);
    }
}

