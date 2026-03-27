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

