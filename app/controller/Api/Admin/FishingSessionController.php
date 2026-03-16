<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\FishingVenue;
use app\model\MiniUser;
use app\model\PondSeat;
use app\model\PondFeeRule;
use app\model\FishingOrder;
use app\model\SystemConfig;
use think\response\Json;

/**
 * 开钓单（经营链路）：列表/详情（只读为主）
 */
class FishingSessionController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/sessions
     * 可选参数：
     * - page, limit
     * - status       ongoing/finished/settled/cancelled
     * - venue_id
     * - pond_id
     * - seat_code
     * - session_no
     */
    public function list(): Json
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed !== null && empty($allowed)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);

        $status = trim((string) $this->request->get('status', ''));
        $venueId = (int) $this->request->get('venue_id', 0);
        $pondId = (int) $this->request->get('pond_id', 0);
        $seatCode = trim((string) $this->request->get('seat_code', ''));
        $sessionNo = trim((string) $this->request->get('session_no', ''));

        $query = FishingSession::order('id', 'desc');

        if ($allowed !== null) {
            $query->whereIn('pond_id', $allowed);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($venueId > 0) {
            $query->where('venue_id', $venueId);
        }
        if ($pondId > 0) {
            $query->where('pond_id', $pondId);
        }
        if ($seatCode !== '') {
            $query->where('seat_code', $seatCode);
        }
        if ($sessionNo !== '') {
            $query->where('session_no', $sessionNo);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows = $paginator->items();

        // 预加载关联名称，避免 N+1
        $pondIds = [];
        $venueIds = [];
        $userIds = [];
        foreach ($rows as $r) {
            if ($r->pond_id) $pondIds[] = (int) $r->pond_id;
            if ($r->venue_id) $venueIds[] = (int) $r->venue_id;
            if ($r->mini_user_id) $userIds[] = (int) $r->mini_user_id;
        }
        $pondIds = array_values(array_unique($pondIds));
        $venueIds = array_values(array_unique($venueIds));
        $userIds = array_values(array_unique($userIds));

        $pondMap = $pondIds ? FishingPond::whereIn('id', $pondIds)->column('name', 'id') : [];
        $venueMap = $venueIds ? FishingVenue::whereIn('id', $venueIds)->column('name', 'id') : [];
        $userMap = $userIds ? MiniUser::whereIn('id', $userIds)->column('nickname', 'id') : [];

        $list = [];
        foreach ($rows as $r) {
            $arr = $r->toArray();
            $arr['venue_name'] = $arr['venue_id'] ? ($venueMap[$arr['venue_id']] ?? '') : '';
            $arr['pond_name'] = $arr['pond_id'] ? ($pondMap[$arr['pond_id']] ?? '') : '';
            $arr['user_nickname'] = $arr['mini_user_id'] ? ($userMap[$arr['mini_user_id']] ?? '') : '';
            $arr['amount_total_yuan'] = round(((int) ($arr['amount_total'] ?? 0)) / 100, 2);
            $arr['amount_paid_yuan'] = round(((int) ($arr['amount_paid'] ?? 0)) / 100, 2);
            $arr['deposit_total_yuan'] = round(((int) ($arr['deposit_total'] ?? 0)) / 100, 2);
            $list[] = $arr;
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $list,
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * 详情 GET /api/admin/sessions/:id
     */
    public function detail(int $id): Json
    {
        $row = FishingSession::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限查看该池塘数据', 'data' => null]);
        }
        $arr = $row->toArray();
        $arr['amount_total_yuan'] = round(((int) ($arr['amount_total'] ?? 0)) / 100, 2);
        $arr['amount_paid_yuan'] = round(((int) ($arr['amount_paid'] ?? 0)) / 100, 2);
        $arr['deposit_total_yuan'] = round(((int) ($arr['deposit_total'] ?? 0)) / 100, 2);
        return json(['code' => 0, 'msg' => 'success', 'data' => $arr]);
    }

    /**
     * 手动开钓单：POST /api/admin/sessions
     * body: mini_user_id, venue_id, pond_id, seat_id?, fee_rule_id, use_balance?, remark?
     */
    public function create(): Json
    {
        $miniUserId = (int) $this->request->post('mini_user_id', 0);
        $venueId    = (int) $this->request->post('venue_id', 0);
        $pondId     = (int) $this->request->post('pond_id', 0);
        $seatId     = (int) $this->request->post('seat_id', 0);
        $feeRuleId  = (int) $this->request->post('fee_rule_id', 0);
        $useBalance = (bool) $this->request->post('use_balance', false);
        $qrEnv      = (string) $this->request->post('qr_env', 'trial'); // trial 或 release
        $remark     = trim((string) $this->request->post('remark', ''));

        if ($miniUserId < 1) {
            return json(['code' => 400, 'msg' => '请选择小程序用户', 'data' => null]);
        }
        if ($venueId < 1 || $pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择钓场和池塘', 'data' => null]);
        }
        /** @var MiniUser|null $user */
        $user = MiniUser::find($miniUserId);
        if (!$user) {
            return json(['code' => 404, 'msg' => '小程序用户不存在', 'data' => null]);
        }
        if (!FishingVenue::find($venueId)) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        /** @var FishingPond|null $pond */
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $seatNo = null;
        $seatCode = null;
        if ($seatId > 0) {
            /** @var PondSeat|null $seat */
            $seat = PondSeat::find($seatId);
            if (!$seat) {
                return json(['code' => 404, 'msg' => '钓位不存在', 'data' => null]);
            }
            if ((int) $seat->pond_id !== $pondId) {
                return json(['code' => 400, 'msg' => '钓位不属于当前池塘', 'data' => null]);
            }
            $seatNo = (int) $seat->seat_no;
            $seatCode = (string) $seat->code;

            // 校验该钓位是否已有未结束的开钓单，避免重复绑定
            $exists = FishingSession::where('seat_id', $seatId)
                ->where('status', 'ongoing')
                ->find();
            if ($exists) {
                return json([
                    'code' => 400,
                    'msg'  => '该钓位当前已有开钓单，不能重复绑定，请先结束或取消原开钓单',
                    'data' => null,
                ]);
            }
        }

        if ($feeRuleId < 1) {
            return json(['code' => 400, 'msg' => '请选择收费规则（正钓/偷驴）', 'data' => null]);
        }

        /** @var PondFeeRule|null $fee */
        $fee = PondFeeRule::find($feeRuleId);
        if (!$fee) {
            return json(['code' => 404, 'msg' => '收费规则不存在', 'data' => null]);
        }
        if ((int) $fee->pond_id !== $pondId) {
            return json(['code' => 400, 'msg' => '收费规则不属于当前池塘', 'data' => null]);
        }

        // 应收金额、押金：严格按收费规则计算（不允许自定义）
        $amountFen = (int) round(((float) ($fee->amount ?? 0)) * 100);
        $depositTotalFen = (int) round(((float) ($fee->deposit ?? 0)) * 100);
        $amountTotalFen = max(0, $amountFen + $depositTotalFen);

        // 自动到期时间：start_time + duration_value/unit
        $expireTime = null;
        $val = $fee->duration_value !== null ? (float) $fee->duration_value : 0;
        $unit = (string) ($fee->duration_unit ?? '');
        if ($val > 0 && ($unit === 'hour' || $unit === 'day')) {
            $seconds = $unit === 'day' ? (int) round($val * 86400) : (int) round($val * 3600);
            if ($seconds > 0) {
                $expireTime = date('Y-m-d H:i:s', time() + $seconds);
            }
        }

        // 会员余额抵扣
        $balanceDeductFen = 0;
        $needPayFen = $amountTotalFen;
        if ($amountTotalFen > 0 && $useBalance && (int) ($user->is_vip ?? 0) === 1) {
            $userBalanceFen = (int) round(((float) ($user->balance ?? 0)) * 100);
            if ($userBalanceFen > 0) {
                $balanceDeductFen = min($userBalanceFen, $amountTotalFen);
                $needPayFen = $amountTotalFen - $balanceDeductFen;
                // 更新用户余额（简单减掉，后续可加余额流水表）
                $user->balance = max(0, ((float) $user->balance) - $balanceDeductFen / 100);
                $user->save();
            }
        }

        // 生成订单：
        // - 若 needPayFen > 0：创建待支付微信订单（amount_total = 需微信支付部分），开钓单由微信回调中创建；
        // - 若 needPayFen == 0：说明已完全用余额抵扣，生成一条余额订单并立即创建开钓单。

        $orderNo = 'O' . date('YmdHis') . mt_rand(1000, 9999);
        if ($needPayFen > 0) {
            $order = FishingOrder::create([
                'order_no'     => $orderNo,
                'mini_user_id' => $miniUserId,
                'venue_id'     => $venueId,
                'pond_id'      => $pondId,
                'seat_id'      => $seatId ?: null,
                'seat_no'      => $seatNo,
                'seat_code'    => $seatCode,
                'fee_rule_id'  => $feeRuleId,
                'description'  => '开钓单预付款',
                'amount_total' => $needPayFen,   // 仅记录需微信支付部分
                'amount_paid'  => 0,
                'status'       => 'pending',
                'pay_channel'  => 'wx_mini',
            ]);
        } else {
            // 全额由余额支付的订单：amount_total 记录应收总额，amount_paid 记录全部已支付金额
            $order = FishingOrder::create([
                'order_no'     => $orderNo,
                'mini_user_id' => $miniUserId,
                'venue_id'     => $venueId,
                'pond_id'      => $pondId,
                'seat_id'      => $seatId ?: null,
                'seat_no'      => $seatNo,
                'seat_code'    => $seatCode,
                'fee_rule_id'  => $feeRuleId,
                'description'  => '开钓单余额支付',
                'amount_total' => $amountTotalFen,
                'amount_paid'  => $amountTotalFen,
                'status'       => 'paid',
                'pay_channel'  => 'balance',
            ]);

            // 余额已全额支付的场景：立即创建开钓单，座位直接占用
            $sessionNo = 'S' . date('YmdHis') . mt_rand(1000, 9999);
            FishingSession::create([
                'session_no'   => $sessionNo,
                'mini_user_id' => $miniUserId,
                'venue_id'     => $venueId,
                'pond_id'      => $pondId,
                'seat_id'      => $seatId ?: null,
                'seat_no'      => $seatNo,
                'seat_code'    => $seatCode,
                'fee_rule_id'  => $feeRuleId,
                'order_id'     => $order->id,
                'start_time'   => date('Y-m-d H:i:s'),
                'expire_time'  => $expireTime,
                'status'       => 'ongoing',
                'amount_total' => $amountTotalFen,
                'amount_paid'  => $amountTotalFen,
                'deposit_total'=> $depositTotalFen,
                'remark'       => $remark !== '' ? $remark : '余额支付自动开钓单',
            ]);
        }

        $amountYuanNeed = round($needPayFen / 100, 2);
        $amountStr = number_format($amountYuanNeed, 2, '.', '');
        $miniPayPath = $needPayFen > 0
            ? '/pages/pay/index?order_no=' . $orderNo . '&amount=' . $amountStr
            : null;

        // 仅在存在需微信支付金额时生成小程序码二维码
        $miniQrUrl = null;
        if ($needPayFen > 0) {
            // 注意：scene 有 32 字节限制，这里只把 order_no 放入 scene，金额通过页面逻辑或订单接口获取
            $miniQrUrl = $this->generateMiniProgramQr($orderNo, $qrEnv);
        }

        $resp = [
            'balance_deduct' => round($balanceDeductFen / 100, 2),
            'need_pay'       => round($needPayFen / 100, 2),
            'order'          => $order ? $order->toArray() : null,
            'mini_pay_path'  => $miniPayPath,
            'mini_qr_url'    => $miniQrUrl,
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => $resp]);
    }

    /**
     * 管理端手动结束开钓单（设为 finished 并写入 end_time）
     * PUT /api/admin/sessions/:id/finish
     */
    public function finish(int $id): Json
    {
        /** @var FishingSession|null $session */
        $session = FishingSession::find($id);
        if (!$session) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $session->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限操作该池塘开钓单', 'data' => null]);
        }
        if ($session->status !== 'ongoing') {
            return json(['code' => 400, 'msg' => '仅进行中的开钓单可结束', 'data' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $session->status = 'finished';
        $session->end_time = $now;
        $session->save();

        return json(['code' => 0, 'msg' => '开钓单已结束', 'data' => $session->toArray()]);
    }

    /**
     * 管理端手动取消开钓单（设为 cancelled 并写入 end_time）
     * PUT /api/admin/sessions/:id/cancel
     */
    public function cancel(int $id): Json
    {
        /** @var FishingSession|null $session */
        $session = FishingSession::find($id);
        if (!$session) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $session->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限操作该池塘开钓单', 'data' => null]);
        }
        if ($session->status !== 'ongoing') {
            return json(['code' => 400, 'msg' => '仅进行中的开钓单可取消', 'data' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $session->status = 'cancelled';
        $session->end_time = $now;
        $session->save();

        return json(['code' => 0, 'msg' => '开钓单已取消', 'data' => $session->toArray()]);
    }

    /**
     * 为后台手动开单生成对应的小程序码二维码
     * 使用小程序接口 getwxacodeunlimit，page 固定为 pages/pay/index，scene 携带订单号
     *
     * @param string $orderNo
     * @param string $envVersion   trial|release
     * @return string|null 返回二维码图片的相对访问路径（例如 /storage/mini_qr/202603/01/xxxx.png）
     */
    private function generateMiniProgramQr(string $orderNo, string $envVersion = 'trial'): ?string
    {
        $miniConfig = config('wechat_mini');
        $appId  = (string) ($miniConfig['appid'] ?? '');
        $secret = (string) ($miniConfig['secret'] ?? '');
        if ($appId === '' || $secret === '') {
            return null;
        }

        // 若 system_config 中配置了小程序 appid/secret，则优先使用
        $cfgAppId  = SystemConfig::getValue('mini_appid', '');
        $cfgSecret = SystemConfig::getValue('mini_secret', '');
        if ($cfgAppId !== '') {
            $appId = $cfgAppId;
        }
        if ($cfgSecret !== '') {
            $secret = $cfgSecret;
        }

        // 获取 access_token（简单实现：每次获取一次；如需优化可改为缓存）
        $tokenUrl = sprintf(
            'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s',
            urlencode($appId),
            urlencode($secret)
        );
        $tokenJson = @file_get_contents($tokenUrl);
        if (!$tokenJson) {
            return null;
        }
        $tokenArr = json_decode($tokenJson, true);
        if (!is_array($tokenArr) || empty($tokenArr['access_token'])) {
            return null;
        }
        $accessToken = (string) $tokenArr['access_token'];

        // 构造小程序码参数
        $page = 'pages/pay/index';
        // scene 长度限制 32，这里仅携带订单号（小程序端可据此查询金额等详细信息）
        $scene = 'order_no=' . $orderNo;

        // env_version：trial（测试版）或 release（正式版），默认为 trial
        $env = $envVersion === 'release' ? 'release' : 'trial';
        $postData = json_encode([
            'page'        => $page,
            'scene'       => $scene,
            'check_path'  => false,
            'env_version' => $env,
        ], JSON_UNESCAPED_UNICODE);

        $qrUrl = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . urlencode($accessToken);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $qrUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($resp === false || !$resp) {
            return null;
        }
        // 如果返回的是 JSON，说明调用失败
        if (is_string($contentType) && stripos($contentType, 'json') !== false) {
            return null;
        }

        // 保存到 public/storage/mini_qr/ 目录
        $subDir = date('Ym') . '/' . date('d');
        $baseDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'mini_qr' . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            return null;
        }
        $fileName = $orderNo . '.png';
        $filePath = $baseDir . DIRECTORY_SEPARATOR . $fileName;
        if (@file_put_contents($filePath, $resp) === false) {
            return null;
        }

        // 对外访问路径（假设 public/storage 映射为 /storage）
        $relativePath = '/storage/mini_qr/' . $subDir . '/' . $fileName;
        return $relativePath;
    }
}

