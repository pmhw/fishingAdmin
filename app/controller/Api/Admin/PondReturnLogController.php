<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingSession;
use app\model\MiniUser;
use app\model\PondReturnLog;
use app\model\PondReturnRule;
use app\service\ReturnLogPayoutService;
use think\response\Json;

/**
 * 回鱼流水（经营链路）：列表、添加、编辑、删除
 */
class PondReturnLogController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/pond-return-logs
     * 可选参数：
     * - page, limit
     * - session_id
     * - pond_id
     * - payout_status
     * - payout_channel
     * - payout_out_bill_no
     */
    public function list(): Json
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed !== null && empty($allowed)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 100);
        $sessionId = (int) $this->request->get('session_id', 0);
        $pondId = (int) $this->request->get('pond_id', 0);
        $payoutStatus = trim((string) $this->request->get('payout_status', ''));
        $payoutChannel = trim((string) $this->request->get('payout_channel', ''));
        $outBillNo = trim((string) $this->request->get('payout_out_bill_no', ''));

        $query = PondReturnLog::order('id', 'desc');
        if ($sessionId > 0) {
            $query->where('session_id', $sessionId);
        }
        if ($pondId > 0) {
            if (!$this->canAccessPond($pondId)) {
                return json(['code' => 403, 'msg' => '无权查看该池塘', 'data' => null]);
            }
            $query->where('pond_id', $pondId);
        }
        if ($allowed !== null) {
            $query->whereIn('pond_id', $allowed);
        }
        if ($payoutStatus !== '') {
            $query->where('payout_status', $payoutStatus);
        }
        if ($payoutChannel !== '') {
            $query->where('payout_channel', $payoutChannel);
        }
        if ($outBillNo !== '') {
            $query->where('payout_out_bill_no', $outBillNo);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $items = $paginator->items();

        // 预加载用户 VIP 信息（用于前端展示“会员走余额/非会员走微信”）
        $sessionIds = [];
        foreach ($items as $r) {
            $sessionIds[] = (int) ($r->session_id ?? 0);
        }
        $sessionIds = array_values(array_unique(array_filter($sessionIds)));
        $sessionMap = $sessionIds ? FishingSession::whereIn('id', $sessionIds)->column('mini_user_id', 'id') : [];
        $userIds = array_values(array_unique(array_filter(array_map('intval', array_values($sessionMap)))));
        $vipMap = $userIds ? MiniUser::whereIn('id', $userIds)->column('is_vip', 'id') : [];

        $list = array_map(static function ($r) use ($sessionMap, $vipMap) {
            $arr = $r->toArray();
            $sid = (int) ($arr['session_id'] ?? 0);
            $uid = $sid > 0 ? (int) ($sessionMap[$sid] ?? 0) : 0;
            $arr['is_vip_user'] = $uid > 0 ? (int) (($vipMap[$uid] ?? 0) == 1) : 0;
            return $arr;
        }, $items);

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => $paginator->total()]]);
    }

    /**
     * 添加 POST /api/admin/pond-return-logs
     * body: session_id, return_type, qty, unit_price, amount, return_rule_id?, remark?
     */
    public function create(): Json
    {
        $sessionId = (int) $this->request->post('session_id', 0);
        $returnType = trim((string) $this->request->post('return_type', 'jin'));
        $qty = (float) $this->request->post('qty', 0);
        $unitPrice = (float) $this->request->post('unit_price', 0);
        $amount = (float) $this->request->post('amount', 0);
        $returnRuleId = $this->request->post('return_rule_id');
        $remark = trim((string) $this->request->post('remark', ''));

        if ($sessionId < 1) {
            return json(['code' => 400, 'msg' => '请传入 session_id', 'data' => null]);
        }
        /** @var FishingSession|null $session */
        $session = FishingSession::find($sessionId);
        if (!$session) {
            return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $session->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        if (!in_array($returnType, ['jin', 'tiao'], true)) {
            return json(['code' => 400, 'msg' => 'return_type 仅支持 jin/tiao', 'data' => null]);
        }

        // 若选择了回鱼规则，则强制以该规则为准（鱼种 + 方式 + 单价）
        $finalRuleId = null;
        if ($returnRuleId !== null && $returnRuleId !== '') {
            $rule = PondReturnRule::find((int) $returnRuleId);
            if (!$rule) {
                return json(['code' => 400, 'msg' => '回鱼规则不存在', 'data' => null]);
            }
            if ((int) $rule->pond_id !== (int) $session->pond_id) {
                return json(['code' => 400, 'msg' => '回鱼规则不属于当前开钓单所在池塘', 'data' => null]);
            }
            $finalRuleId = (int) $rule->id;
            $returnType = (string) $rule->return_type;
            if (!in_array($returnType, ['jin', 'tiao'], true)) {
                return json(['code' => 400, 'msg' => '回鱼规则的 return_type 非法', 'data' => null]);
            }
            $unitPrice = (float) $rule->amount;
            $amount = $qty * $unitPrice;
        } else {
            // 未选择规则时：允许按手工填写的单价/金额保存
            $finalRuleId = null;
            if ($unitPrice > 0 && $qty > 0) {
                $amount = $qty * $unitPrice;
            }
        }

        $row = PondReturnLog::create([
            'session_id'      => $sessionId,
            'venue_id'        => (int) $session->venue_id,
            'pond_id'         => (int) $session->pond_id,
            'return_rule_id'  => $finalRuleId,
            'return_type'     => $returnType,
            'qty'             => $qty,
            'unit_price'      => $unitPrice,
            'amount'          => $amount,
            'remark'          => $remark,
        ]);

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $row->toArray()]);
    }

    /**
     * 编辑 PUT /api/admin/pond-return-logs/:id
     */
    public function update(int $id): Json
    {
        $row = PondReturnLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '回鱼流水不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $returnType = $this->request->param('return_type');
        $qty = $this->request->param('qty');
        $unitPrice = $this->request->param('unit_price');
        $amount = $this->request->param('amount');
        $returnRuleId = $this->request->param('return_rule_id');
        $remark = $this->request->param('remark');

        if ($returnType !== null && $returnType !== '') {
            if (!in_array($returnType, ['jin', 'tiao'], true)) {
                return json(['code' => 400, 'msg' => 'return_type 仅支持 jin/tiao', 'data' => null]);
            }
            $row->return_type = $returnType;
        }
        if ($qty !== null) $row->qty = (float) $qty;
        if ($unitPrice !== null) $row->unit_price = (float) $unitPrice;
        if ($amount !== null) $row->amount = (float) $amount;
        if ($returnRuleId !== null) {
            $row->return_rule_id = $returnRuleId === '' ? null : (int) $returnRuleId;
        }
        if ($remark !== null) $row->remark = trim((string) $remark);

        $row->save();
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $row->toArray()]);
    }

    /**
     * 删除 DELETE /api/admin/pond-return-logs/:id
     */
    public function delete(int $id): Json
    {
        $row = PondReturnLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '回鱼流水不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }

    /**
     * 发起打款（会员入余额 / 非会员微信转账）
     * POST /api/admin/pond-return-logs/:id/payout
     */
    public function payout(int $id): Json
    {
        $row = PondReturnLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '回鱼流水不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        try {
            $result = ReturnLogPayoutService::payout($id);
            return json(['code' => 0, 'msg' => $result['msg'] ?? 'success', 'data' => $result]);
        } catch (\Throwable $e) {
            return json(['code' => 400, 'msg' => $e->getMessage(), 'data' => null]);
        }
    }
}

