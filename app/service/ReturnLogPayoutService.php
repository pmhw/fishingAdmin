<?php
declare(strict_types=1);

namespace app\service;

use app\model\FishingSession;
use app\model\MiniUser;
use app\model\PondReturnLog;
use app\model\SystemConfig;
use think\facade\Db;

/**
 * 回鱼流水打款：会员走余额，非会员走微信转账（v3 转账到零钱）
 */
class ReturnLogPayoutService
{
    /**
     * 发起打款（幂等：已 success/pending 不重复）
     *
     * @throws \RuntimeException
     */
    public static function payout(int $returnLogId): array
    {
        return Db::transaction(function () use ($returnLogId) {
            /** @var PondReturnLog|null $log */
            $log = PondReturnLog::where('id', $returnLogId)->lock(true)->find();
            if (!$log) {
                throw new \RuntimeException('回鱼流水不存在');
            }
            $status = (string) ($log->payout_status ?? 'none');
            if ($status === 'success') {
                return ['mode' => 'noop', 'msg' => '已打款'];
            }
            if ($status === 'pending') {
                return ['mode' => 'noop', 'msg' => '打款处理中'];
            }

            $amount = (float) ($log->payout_amount ?? 0);
            if ($amount <= 0) {
                $amount = (float) ($log->amount ?? 0);
            }
            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw new \RuntimeException('打款金额必须大于 0');
            }

            /** @var FishingSession|null $session */
            $session = FishingSession::find((int) $log->session_id);
            if (!$session) {
                throw new \RuntimeException('关联开钓单不存在');
            }
            /** @var MiniUser|null $user */
            $user = MiniUser::where('id', (int) $session->mini_user_id)->lock(true)->find();
            if (!$user) {
                throw new \RuntimeException('关联用户不存在');
            }

            $isVip = (int) ($user->is_vip ?? 0) === 1;
            if ($isVip) {
                $user->balance = round((float) ($user->balance ?? 0) + $amount, 2);
                $user->save();

                $log->save([
                    'payout_status' => 'success',
                    'payout_channel' => 'balance',
                    'payout_amount' => $amount,
                    'payout_time' => date('Y-m-d H:i:s'),
                    'payout_fail_reason' => null,
                ]);

                return ['mode' => 'balance', 'msg' => '已入账会员余额'];
            }

            $openid = (string) ($user->openid ?? '');
            if ($openid === '') {
                throw new \RuntimeException('用户缺少 openid，无法微信转账');
            }

            $mchId = (string) SystemConfig::getValue('pay_mch_id', '');
            $serialNo = (string) SystemConfig::getValue('wxpay_v3_serial_no', '');
            $privateKey = (string) SystemConfig::getValue('wxpay_v3_private_key_pem', '');
            $appId = (string) SystemConfig::getValue('wxpay_v3_appid', '');
            if ($mchId === '' || $serialNo === '' || $privateKey === '' || $appId === '') {
                throw new \RuntimeException('微信转账未配置（pay_mch_id/wxpay_v3_serial_no/wxpay_v3_private_key_pem/wxpay_v3_appid）');
            }

            $notifyUrl = (string) SystemConfig::getValue('wxpay_v3_transfer_notify_url', '');
            $sceneId = (string) SystemConfig::getValue('wxpay_v3_transfer_scene_id', '1000');

            $outBillNo = (string) ($log->payout_out_bill_no ?? '');
            if ($outBillNo === '') {
                $outBillNo = 'RL' . date('YmdHis') . sprintf('%06d', (int) $log->id) . random_int(1000, 9999);
            }

            $payload = [
                'out_bill_no' => $outBillNo,
                'openid' => $openid,
                'transfer_amount' => (int) round($amount * 100), // 分
                'transfer_remark' => '回鱼打款',
                'transfer_scene_id' => $sceneId !== '' ? $sceneId : '1000',
                'user_recv_perception' => '回鱼打款',
            ];
            if ($notifyUrl !== '') {
                $payload['notify_url'] = $notifyUrl;
            }
            // 报备信息（可选）
            $payload['transfer_scene_report_infos'] = [
                ['info_type' => '业务名称', 'info_content' => '回鱼流水打款'],
                ['info_type' => '回鱼流水ID', 'info_content' => (string) $log->id],
            ];

            $client = new WechatPayV3Client($mchId, $serialNo, $privateKey, $appId);
            $resp = $client->transferBills($payload);

            $raw = json_encode($resp, JSON_UNESCAPED_UNICODE);
            $httpCode = (int) ($resp['http_code'] ?? 0);
            $body = $resp['body'] ?? null;

            if ($httpCode >= 200 && $httpCode < 300) {
                // 用户确认模式：需要 body.package_info，由小程序 wx.requestMerchantTransfer 拉起确认页
                $log->save([
                    'payout_status' => 'pending',
                    'payout_channel' => 'wechat',
                    'payout_amount' => $amount,
                    'payout_out_bill_no' => $outBillNo,
                    'payout_raw' => $raw,
                    'payout_fail_reason' => null,
                ]);
                return ['mode' => 'wechat_confirm', 'msg' => '已发起微信转账（待用户在小程序确认收款）', 'out_bill_no' => $outBillNo];
            }

            $failMsg = '';
            if (is_array($body)) {
                $failMsg = (string) ($body['message'] ?? $body['msg'] ?? '');
            } elseif (is_string($body)) {
                $failMsg = $body;
            }

            $log->save([
                'payout_status' => 'failed',
                'payout_channel' => 'wechat',
                'payout_amount' => $amount,
                'payout_out_bill_no' => $outBillNo,
                'payout_raw' => $raw,
                'payout_fail_reason' => $failMsg !== '' ? mb_substr($failMsg, 0, 240) : ('HTTP ' . $httpCode),
            ]);

            throw new \RuntimeException('微信转账发起失败：' . ($failMsg !== '' ? $failMsg : ('HTTP ' . $httpCode)));
        });
    }
}

