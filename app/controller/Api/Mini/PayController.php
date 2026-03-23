<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\FishingOrder;
use app\model\FishingSession;
use app\model\VenueShopOrder;
use app\model\FishingPond;
use app\model\PondSeat;
use app\model\MiniUser;
use app\model\PondFeeRule;
use app\model\Activity;
use app\model\ActivityParticipation;
use app\model\SystemConfig;
use think\response\Json;

/**
 * 小程序端 - 微信支付（JSAPI，v2 统一下单）
 *
 * 说明：
 * - 使用小程序登录获得的 token（MiniAuth 中间件）确定当前用户，再根据其 openid 统一下单。
 * - 你需要在 .env 中配置：
 *   - WECHAT_MINI_APPID       小程序 AppID
 *   - WECHAT_PAY_MCH_ID      商户号
 *   - WECHAT_PAY_KEY         API v2 密钥
 *   - WECHAT_PAY_NOTIFY_URL  支付回调地址（https）
 *
     * 接口：
     * - POST /api/mini/pay/wechat/jsapi   （需登录）
     *   请求体 JSON:
     *   {
     *     order_no?, description, total_fee,
     *     venue_id?, pond_id?, seat_id?, seat_no?, seat_code?,
     *     fee_rule_id?, return_rule_id?
     *   }
     *   - 若 order_no 为空，则自动生成一个，并在 fishing_order 中创建新订单；
     *   - 若 order_no 已存在，则直接使用已有订单的金额和描述，忽略本次 total_fee；
     *   返回 data 为 wx.requestPayment 所需参数
 *
 * - POST /api/mini/pay/wechat/notify  （微信服务器回调，XML）
 *   你可以在这里根据 out_trade_no 更新业务订单状态
 */
class PayController extends MiniBaseController
{
    /**
     * 统一下单并返回小程序端支付参数
     * POST /api/mini/pay/wechat/jsapi
     */
    public function jsapi(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) {
            return $error;
        }
        /** @var MiniUser $user */

        $orderNo     = trim((string) $this->request->post('order_no', ''));
        $description = trim((string) $this->request->post('description', ''));
        $totalFee    = (int) $this->request->post('total_fee', 0); // 单位：分

        // 关联业务信息（可选）
        $venueId      = (int) $this->request->post('venue_id', 0);
        $pondId       = (int) $this->request->post('pond_id', 0);
        $seatId       = (int) $this->request->post('seat_id', 0);
        $seatNo       = (int) $this->request->post('seat_no', 0);
        $seatCode     = trim((string) $this->request->post('seat_code', ''));
        $feeRuleId    = (int) $this->request->post('fee_rule_id', 0);
        $returnRuleId = (int) $this->request->post('return_rule_id', 0);

        if ($description === '') {
            return json(['code' => 400, 'msg' => '缺少订单描述', 'data' => null]);
        }

        $miniConfig = config('wechat_mini');
        $payConfig  = config('wechat_pay');
        $appId      = (string) ($miniConfig['appid'] ?? '');
        // 先从环境配置读取，若 system_config 中有值则覆盖
        $mchId      = (string) ($payConfig['mch_id'] ?? '');
        $key        = (string) ($payConfig['key'] ?? '');
        $dbMchId    = SystemConfig::getValue('pay_mch_id', '');
        $dbKey      = SystemConfig::getValue('pay_key', '');
        if ($dbMchId !== '') {
            $mchId = $dbMchId;
        }
        if ($dbKey !== '') {
            $key = $dbKey;
        }
        // 优先使用全局配置表 system_config 中的 pay_notify_url，其次使用 config/wechat_pay.php 中的 notify_url
        $notifyUrlDb = SystemConfig::getValue('pay_notify_url', '');
        $notifyUrl   = $notifyUrlDb !== '' ? $notifyUrlDb : (string) ($payConfig['notify_url'] ?? '');

        if ($appId === '' || $mchId === '' || $key === '' || $notifyUrl === '') {
            return json(['code' => 500, 'msg' => '微信支付未正确配置（appid/mchid/key/notify_url）', 'data' => null]);
        }

        $openid = $this->request->miniOpenid ?? '';
        if ($openid === '') {
            return json(['code' => 401, 'msg' => '未登录', 'data' => null]);
        }

        $clientIp = (string) $this->request->ip();

        // 1. 创建或获取订单
        if ($orderNo === '') {
            $orderNo = date('YmdHis') . sprintf('%04d', (int) $user->id) . random_int(1000, 9999);
        }

        /** @var FishingOrder|null $order */
        $order = FishingOrder::where('order_no', $orderNo)->find();
        if ($order) {
            // 所有权校验：只允许创建该订单的用户再次发起支付
            if ((int) $order->mini_user_id !== (int) $user->id) {
                return json(['code' => 403, 'msg' => '无权操作该订单', 'data' => null]);
            }
            if ((string) $order->status !== 'pending') {
                $msg = (string) $order->status === 'timeout' ? '订单已超时，请重新下单' : '订单状态已变更，无法再次发起支付';
                return json(['code' => 400, 'msg' => $msg, 'data' => null]);
            }
            // 使用已有订单金额与描述，忽略本次 total_fee，防止前端篡改
            $totalFee    = (int) $order->amount_total;
            $description = $order->description ?: $description;
        } else {
            // 首次创建订单：金额应由服务端根据计费规则计算，尽量不信任前端 total_fee
            if ($feeRuleId > 0) {
                /** @var PondFeeRule|null $feeRule */
                $feeRule = PondFeeRule::find($feeRuleId);
                if (!$feeRule) {
                    return json(['code' => 400, 'msg' => '收费规则不存在', 'data' => null]);
                }
                if ($pondId > 0 && (int) $feeRule->pond_id !== $pondId) {
                    return json(['code' => 400, 'msg' => '收费规则不属于该池塘', 'data' => null]);
                }
                // 以收费规则金额为准（元转分）
                $totalFee = (int) round(((float) $feeRule->amount) * 100);
            }
            // 如果仍然没有金额（例如没有选择收费规则），则回退使用前端传入的 total_fee，但需校验 > 0
            if ($totalFee <= 0) {
                return json(['code' => 400, 'msg' => '金额必须大于 0', 'data' => null]);
            }
            $order = FishingOrder::create([
                'order_no'      => $orderNo,
                'mini_user_id'  => (int) $user->id,
                'venue_id'      => $venueId > 0 ? $venueId : null,
                'pond_id'       => $pondId > 0 ? $pondId : null,
                'seat_id'       => $seatId > 0 ? $seatId : null,
                'seat_no'       => $seatNo > 0 ? $seatNo : null,
                'seat_code'     => $seatCode !== '' ? $seatCode : null,
                'fee_rule_id'   => $feeRuleId > 0 ? $feeRuleId : null,
                'return_rule_id'=> $returnRuleId > 0 ? $returnRuleId : null,
                'description'   => $description,
                'amount_total'  => $totalFee,
                'amount_paid'   => 0,
                'status'        => 'pending',
                'pay_channel'   => 'wx_mini',
            ]);
        }

        if ($totalFee <= 0) {
            return json(['code' => 400, 'msg' => '订单金额异常', 'data' => null]);
        }

        // 统一下单
        $nonceStr = bin2hex(random_bytes(16));
        $params   = [
            'appid'            => $appId,
            'mch_id'           => $mchId,
            'nonce_str'        => $nonceStr,
            'body'             => mb_substr($description, 0, 40),
            'out_trade_no'     => $orderNo,
            'total_fee'        => $totalFee,
            'spbill_create_ip' => $clientIp,
            'notify_url'       => $notifyUrl,
            'trade_type'       => 'JSAPI',
            'openid'           => $openid,
        ];
        $params['sign'] = $this->makeSign($params, $key);

        $xml = $this->arrayToXml($params);

        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $respXml = $this->postXml($url, $xml, 10);
        if ($respXml === null) {
            return json(['code' => 500, 'msg' => '请求微信统一下单失败', 'data' => null]);
        }
        $respArr = $this->xmlToArray($respXml);
        if (!is_array($respArr) || ($respArr['return_code'] ?? '') !== 'SUCCESS') {
            $msg = (string) ($respArr['return_msg'] ?? '统一下单失败');
            return json(['code' => 500, 'msg' => '统一下单失败：' . $msg, 'data' => $respArr]);
        }
        if (($respArr['result_code'] ?? '') !== 'SUCCESS') {
            $err = ($respArr['err_code_des'] ?? $respArr['err_code'] ?? '统一下单失败');
            return json(['code' => 500, 'msg' => '统一下单失败：' . $err, 'data' => $respArr]);
        }

        $prepayId = (string) ($respArr['prepay_id'] ?? '');
        if ($prepayId === '') {
            return json(['code' => 500, 'msg' => '统一下单成功但缺少 prepay_id', 'data' => $respArr]);
        }

        // 生成小程序端支付参数
        $timeStamp = (string) time();
        $payNonce  = bin2hex(random_bytes(16));
        $package   = 'prepay_id=' . $prepayId;
        $signType  = 'MD5';

        $paySignParams = [
            'appId'     => $appId,
            'timeStamp' => $timeStamp,
            'nonceStr'  => $payNonce,
            'package'   => $package,
            'signType'  => $signType,
        ];
        $paySign = $this->makeSign($paySignParams, $key);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'timeStamp' => $timeStamp,
                'nonceStr'  => $payNonce,
                'package'   => $package,
                'signType'  => $signType,
                'paySign'   => $paySign,
                // 下面两个字段便于前端调试/查询
                'out_trade_no' => $orderNo,
                'prepay_id'    => $prepayId,
            ],
        ]);
    }

    /**
     * 微信支付结果回调
     * POST /api/mini/pay/wechat/notify
     * 微信会以 XML 形式请求本接口
     */
    public function notify()
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $this->notifyResponse('FAIL', 'Empty body');
        }
        $data = $this->xmlToArray($raw);
        if (!is_array($data)) {
            return $this->notifyResponse('FAIL', 'XML parse error');
        }

        $payConfig = config('wechat_pay');
        // key 也支持从 system_config 覆盖
        $key = (string) ($payConfig['key'] ?? '');
        $dbKey = SystemConfig::getValue('pay_key', '');
        if ($dbKey !== '') {
            $key = $dbKey;
        }
        if ($key === '') {
            return $this->notifyResponse('FAIL', 'pay key not configured');
        }

        $sign = $data['sign'] ?? '';
        unset($data['sign']);
        $calcSign = $this->makeSign($data, $key);
        if ($sign === '' || $sign !== $calcSign) {
            return $this->notifyResponse('FAIL', 'Invalid sign');
        }

        if (($data['return_code'] ?? '') !== 'SUCCESS' || ($data['result_code'] ?? '') !== 'SUCCESS') {
            // 可根据需要记录失败原因
            return $this->notifyResponse('SUCCESS', 'OK'); // 仍然返回 SUCCESS，避免重复通知
        }

        // 校验 appid 和 mch_id 与本地配置一致
        $miniConfig = config('wechat_mini');
        $localAppId = (string) ($miniConfig['appid'] ?? '');
        $localMchId = (string) ($payConfig['mch_id'] ?? '');
        if ($localAppId !== '' && isset($data['appid']) && (string) $data['appid'] !== $localAppId) {
            return $this->notifyResponse('FAIL', 'AppID mismatch');
        }
        if ($localMchId !== '' && isset($data['mch_id']) && (string) $data['mch_id'] !== $localMchId) {
            return $this->notifyResponse('FAIL', 'MchID mismatch');
        }

        $outTradeNo    = (string) ($data['out_trade_no'] ?? '');
        $totalFee      = (int) ($data['total_fee'] ?? 0);
        $transactionId = (string) ($data['transaction_id'] ?? '');

        if ($outTradeNo === '') {
            return $this->notifyResponse('FAIL', 'Missing out_trade_no');
        }

        /** @var FishingOrder|null $order */
        $order = FishingOrder::where('order_no', $outTradeNo)->find();
        if (!$order) {
            // 找不到订单也返回 SUCCESS，避免微信反复通知；同时可在日志中排查
            return $this->notifyResponse('SUCCESS', 'ORDER_NOT_FOUND');
        }

        // 幂等：如果已经标记为已支付，则直接返回 SUCCESS
        if ($order->status === 'paid') {
            return $this->notifyResponse('SUCCESS', 'OK');
        }

        // 非待支付（超时/关闭/退款等）：不再入账，返回 SUCCESS 避免微信无限重试
        if ((string) $order->status !== 'pending') {
            return $this->notifyResponse('SUCCESS', 'ORDER_NOT_PENDING');
        }

        // 校验金额是否一致（不一致时记录但不反复拒绝，避免微信持续重试）
        if ((int) $order->amount_total !== $totalFee) {
            $order->save([
                'raw_notify' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
            return $this->notifyResponse('SUCCESS', 'AMOUNT_MISMATCH');
        }

        $now = date('Y-m-d H:i:s');
        $order->save([
            'status'        => 'paid',
            'amount_paid'   => $totalFee,
            'pay_trade_no'  => $transactionId !== '' ? $transactionId : $order->pay_trade_no,
            'pay_time'      => $now,
            'raw_notify'    => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        // 店铺订单：同步 venue_shop_order（单号 SO 开头或与描述一致）
        $desc = (string) ($order->description ?? '');
        if (str_starts_with($outTradeNo, 'SO') || str_contains($desc, '店铺订单')) {
            try {
                /** @var VenueShopOrder|null $shopOrder */
                $shopOrder = VenueShopOrder::where('order_no', $outTradeNo)->find();
                if ($shopOrder && (string) $shopOrder->status === 'pending') {
                    $shopOrder->save([
                        'status'       => 'paid',
                        'pay_trade_no' => $transactionId !== '' ? $transactionId : $shopOrder->pay_trade_no,
                        'pay_time'     => $now,
                        'raw_notify'   => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            } catch (\Throwable $e) {
                // 不影响向微信返回 SUCCESS
            }
        }

        // 若该订单用于开钓单预付款，则在支付成功后创建 fishing_session（开单前必须先支付）
        try {
            $this->createSessionAfterPaid($order);
        } catch (\Throwable $e) {
            // 业务异常不影响向微信返回 SUCCESS，避免重复回调
        }

        // 活动报名订单：支付后同步参与状态，并按抽号模式分配座位/创建 session
        try {
            $this->createActivityAfterPaid($order);
        } catch (\Throwable $e) {
            // 业务异常不影响向微信返回 SUCCESS，避免重复回调
        }

        return $this->notifyResponse('SUCCESS', 'OK');
    }

    /**
     * 生成签名（MD5）
     *
     * @param array $params  待签名参数
     * @param string $key    商户密钥
     */
    private function makeSign(array $params, string $key): string
    {
        ksort($params);
        $buff = [];
        foreach ($params as $k => $v) {
            if ($v === '' || $v === null || $k === 'sign') {
                continue;
            }
            $buff[] = $k . '=' . $v;
        }
        $string = implode('&', $buff) . '&key=' . $key;
        return strtoupper(md5($string));
    }

    /**
     * 数组转 XML
     */
    private function arrayToXml(array $data): string
    {
        $xml = '<xml>';
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $xml .= "<{$key}>{$value}</{$key}>";
            } else {
                $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>";
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    /**
     * XML 转数组
     *
     * @return array<string,mixed>|null
     */
    private function xmlToArray(string $xml): ?array
    {
        $res = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($res === false) {
            return null;
        }
        return json_decode(json_encode($res, JSON_UNESCAPED_UNICODE), true);
    }

    /**
     * 发送 XML POST 请求
     */
    private function postXml(string $url, string $xml, int $timeout = 10): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            curl_close($ch);
            return null;
        }
        curl_close($ch);
        return (string) $resp;
    }

    /**
     * 回调响应 XML
     */
    private function notifyResponse(string $code, string $msg)
    {
        $data = [
            'return_code' => $code,
            'return_msg'  => $msg,
        ];
        $xml = $this->arrayToXml($data);
        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * 根据已支付订单创建开钓单 session（仅针对“开钓单预付款”类型订单）
     *
     * @param FishingOrder $order
     */
    private function createSessionAfterPaid(FishingOrder $order): void
    {
        // 仅对描述中包含“开钓单预付款”的订单处理，避免影响其他业务订单
        $desc = (string) ($order->description ?? '');
        if ($desc === '' || mb_strpos($desc, '开钓单预付款') === false) {
            return;
        }

        $miniUserId = (int) ($order->mini_user_id ?? 0);
        $venueId    = (int) ($order->venue_id ?? 0);
        $pondId     = (int) ($order->pond_id ?? 0);
        $seatId     = (int) ($order->seat_id ?? 0);
        $seatNo     = (int) ($order->seat_no ?? 0);
        $seatCode   = (string) ($order->seat_code ?? '');
        $feeRuleId  = (int) ($order->fee_rule_id ?? 0);

        if ($miniUserId < 1 || $venueId < 1 || $pondId < 1 || $feeRuleId < 1) {
            return;
        }

        // 校验池塘、钓位、收费规则是否存在且关联正确
        /** @var FishingPond|null $pond */
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return;
        }
        if ((int) $pond->venue_id !== $venueId) {
            return;
        }

        $seatNoVal = $seatNo > 0 ? $seatNo : null;
        $seatCodeVal = $seatCode !== '' ? $seatCode : null;
        if ($seatId > 0) {
            /** @var PondSeat|null $seat */
            $seat = PondSeat::find($seatId);
            if (!$seat) {
                return;
            }
            if ((int) $seat->pond_id !== $pondId) {
                return;
            }
            $seatNoVal = (int) $seat->seat_no;
            $seatCodeVal = (string) $seat->code;

            // 再次校验该钓位是否已有未结束的开钓单，避免重复占用
            $exists = FishingSession::where('seat_id', $seatId)
                ->where('status', 'ongoing')
                ->find();
            if ($exists) {
                return;
            }
        }

        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee || (int) $fee->pond_id !== $pondId) {
            return;
        }

        // 计算应收金额（规则金额 + 押金）
        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $amountTotalFen = max(0, $amountFen + $depositFen);

        // 订单金额为需支付部分，余额抵扣 = 应收 - 需付（如为会员并使用余额）
        $needPayFen = (int) ($order->amount_total ?? 0);
        $balanceDeductFen = max(0, $amountTotalFen - $needPayFen);
        $amountPaidFen = $needPayFen + $balanceDeductFen;

        // 自动到期时间
        $expireTime = null;
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                $expireTime = date('Y-m-d H:i:s', time() + $seconds);
            }
        }

        // 幂等：避免重复为同一订单创建多个 session（如果 order 表有 session_id，可在此判断）

        $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);
        $session = FishingSession::create([
            'session_no'   => $sessionNo,
            'mini_user_id' => $miniUserId,
            'venue_id'     => $venueId,
            'pond_id'      => $pondId,
            'seat_id'      => $seatId ?: null,
            'seat_no'      => $seatNoVal,
            'seat_code'    => $seatCodeVal,
            'fee_rule_id'  => $feeRuleId,
            'order_id'     => $order->id,
            'start_time'   => date('Y-m-d H:i:s'),
            'expire_time'  => $expireTime,
            'status'       => 'ongoing',
            'amount_total' => $amountTotalFen,
            'amount_paid'  => $amountPaidFen,
            'deposit_total'=> $depositFen,
            'remark'       => '支付成功自动开钓单',
        ]);

        // 如表结构支持，可反写 session_id 到订单，方便关联（此处仅在字段存在时设置）
        try {
            if ($order->hasField('session_id')) {
                $order->session_id = $session->id;
                $order->save();
            }
        } catch (\Throwable $e) {
            // 忽略该错误
        }
    }

    /**
     * 活动报名预付款：支付成功后同步参与记录。
     * - unified/offline：仅标记待抽号
     * - random/self_pick：立即分配座位并创建 fishing_session（start_time=open_time）
     */
    private function createActivityAfterPaid(FishingOrder $order): void
    {
        $desc = (string) ($order->description ?? '');
        if ($desc === '' || mb_strpos($desc, '活动报名预付款') === false) {
            return;
        }

        $orderNo = (string) ($order->order_no ?? '');
        $miniUserId = (int) ($order->mini_user_id ?? 0);
        if ($orderNo === '' || $miniUserId < 1) {
            return;
        }

        /** @var ActivityParticipation|null $part */
        $part = ActivityParticipation::where('pay_order_no', $orderNo)
            ->where('mini_user_id', $miniUserId)
            ->find();
        if (!$part) {
            return;
        }

        if ((string) ($part->pay_status ?? '') !== 'paid') {
            $part->pay_status = 'paid';
            $part->save();
        }

        $activityId = (int) ($part->activity_id ?? 0);
        if ($activityId < 1) {
            return;
        }
        /** @var Activity|null $activity */
        $activity = Activity::find($activityId);
        if (!$activity) {
            return;
        }

        $drawMode = (string) ($activity->draw_mode ?? 'random');
        if ($drawMode === 'unified') {
            $part->draw_status = 'draw_waiting_unified';
            $part->save();
            return;
        }
        if ($drawMode === 'offline') {
            $part->draw_status = 'draw_waiting_offline';
            $part->save();
            return;
        }
        if (!empty($part->assigned_session_id) || !empty($part->assigned_seat_id)) {
            return;
        }

        $pondId = (int) ($activity->pond_id ?? 0);
        $feeRuleId = (int) ($part->fee_rule_id ?? 0);
        if ($pondId < 1 || $feeRuleId < 1) {
            return;
        }
        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return;
        }

        $occupiedSeatIds = FishingSession::where('pond_id', $pondId)
            ->where('status', 'ongoing')
            ->where('seat_id', '>', 0)
            ->column('seat_id');
        $occupiedSeatIds = array_flip(array_map('intval', is_array($occupiedSeatIds) ? $occupiedSeatIds : []));

        $assignedSeatIds = ActivityParticipation::where('activity_id', $activityId)
            ->whereNotNull('assigned_seat_id')
            ->where('assigned_seat_id', '>', 0)
            ->column('assigned_seat_id');
        $assignedSeatIds = array_flip(array_map('intval', is_array($assignedSeatIds) ? $assignedSeatIds : []));

        $seatId = 0;
        $seatNo = 0;
        $seatCode = '';
        if ($drawMode === 'self_pick') {
            $desiredSeatNo = (int) ($part->desired_seat_no ?? 0);
            if ($desiredSeatNo < 1) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
            /** @var PondSeat|null $seat */
            $seat = PondSeat::where('pond_id', $pondId)->where('seat_no', $desiredSeatNo)->find();
            if (!$seat) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
            $seatId = (int) ($seat->id ?? 0);
            $seatNo = (int) ($seat->seat_no ?? 0);
            $seatCode = (string) ($seat->code ?? '');
            if ($seatId < 1 || isset($occupiedSeatIds[$seatId]) || isset($assignedSeatIds[$seatId])) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
        } else {
            $seats = PondSeat::where('pond_id', $pondId)->order('seat_no', 'asc')->select();
            $free = [];
            foreach ($seats as $s) {
                $sid = (int) ($s->id ?? 0);
                if ($sid < 1) {
                    continue;
                }
                if (isset($occupiedSeatIds[$sid]) || isset($assignedSeatIds[$sid])) {
                    continue;
                }
                $free[] = $s;
            }
            if (empty($free)) {
                $part->draw_status = 'cancelled';
                $part->save();
                return;
            }
            $pick = $free[random_int(0, count($free) - 1)];
            $seatId = (int) ($pick->id ?? 0);
            $seatNo = (int) ($pick->seat_no ?? 0);
            $seatCode = (string) ($pick->code ?? '');
        }

        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $amountTotalFen = max(0, $amountFen + $depositFen);
        $amountPaidFen = (int) ($order->amount_paid ?? $amountTotalFen);

        $expireTime = null;
        $durationValue = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $durationUnit = (string) ($fee->duration_unit ?? '');
        if ($durationValue > 0 && ($durationUnit === 'hour' || $durationUnit === 'day')) {
            $seconds = $durationUnit === 'day' ? (int) round($durationValue * 86400) : (int) round($durationValue * 3600);
            if ($seconds > 0) {
                $expireTime = date('Y-m-d H:i:s', strtotime((string) $activity->open_time) + $seconds);
            }
        }

        $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);
        $session = FishingSession::create([
            'session_no'    => $sessionNo,
            'mini_user_id'  => $miniUserId,
            'venue_id'      => (int) ($order->venue_id ?? 0),
            'pond_id'       => $pondId,
            'seat_id'       => $seatId,
            'seat_no'       => $seatNo,
            'seat_code'     => $seatCode,
            'fee_rule_id'   => $feeRuleId,
            'order_id'      => (int) ($order->id ?? 0),
            'start_time'    => (string) ($activity->open_time ?? date('Y-m-d H:i:s')),
            'expire_time'   => $expireTime,
            'status'        => 'ongoing',
            'amount_total'  => $amountTotalFen,
            'amount_paid'   => $amountPaidFen,
            'deposit_total' => $depositFen,
            'remark'        => '活动支付成功占座',
        ]);

        $part->assigned_seat_id = $seatId;
        $part->assigned_seat_no = $seatNo;
        $part->assigned_session_id = (int) ($session->id ?? 0);
        $part->draw_status = 'assigned';
        $part->save();
    }
}

