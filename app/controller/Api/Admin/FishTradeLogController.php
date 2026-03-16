<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishTradeLog;
use app\model\FishingSession;
use think\response\Json;

/**
 * 卖鱼/收鱼流水（经营链路）：列表、添加、编辑、删除
 */
class FishTradeLogController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/fish-trade-logs
     * 可选参数：
     * - page, limit
     * - session_id
     * - trade_type  buy_in / sell_out
     * - pond_id
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
        $tradeType = trim((string) $this->request->get('trade_type', ''));
        $pondId = (int) $this->request->get('pond_id', 0);

        $query = FishTradeLog::order('id', 'desc');

        if ($sessionId > 0) $query->where('session_id', $sessionId);
        if ($tradeType !== '') $query->where('trade_type', $tradeType);
        if ($pondId > 0) $query->where('pond_id', $pondId);
        if ($allowed !== null) $query->whereIn('pond_id', $allowed);

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $list = [];
        foreach ($paginator->items() as $r) {
            $arr = $r->toArray();
            if (is_string($arr['images'] ?? null) && $arr['images'] !== '') {
                $decoded = json_decode($arr['images'], true);
                $arr['images'] = is_array($decoded) ? $decoded : [];
            } else {
                $arr['images'] = [];
            }
            $list[] = $arr;
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => $paginator->total()]]);
    }

    /**
     * 添加 POST /api/admin/fish-trade-logs
     * body: venue_id, pond_id?, session_id?, mini_user_id?, trade_type, unit, qty, unit_price, amount, images?, remark?
     * images: string[]
     */
    public function create(): Json
    {
        $sessionId = $this->request->post('session_id');
        $miniUserId = $this->request->post('mini_user_id');
        $venueId = (int) $this->request->post('venue_id', 0);
        $pondId = $this->request->post('pond_id');
        $tradeType = trim((string) $this->request->post('trade_type', 'buy_in'));
        $unit = trim((string) $this->request->post('unit', 'jin'));
        $qty = (float) $this->request->post('qty', 0);
        $unitPrice = (float) $this->request->post('unit_price', 0);
        $amount = (float) $this->request->post('amount', 0);
        $images = $this->request->post('images', []);
        $remark = trim((string) $this->request->post('remark', ''));

        if (!in_array($tradeType, ['buy_in', 'sell_out'], true)) {
            return json(['code' => 400, 'msg' => 'trade_type 仅支持 buy_in/sell_out', 'data' => null]);
        }
        if (!in_array($unit, ['jin', 'tiao'], true)) {
            return json(['code' => 400, 'msg' => 'unit 仅支持 jin/tiao', 'data' => null]);
        }
        if ($venueId < 1) {
            return json(['code' => 400, 'msg' => '请传入 venue_id', 'data' => null]);
        }

        $pondIdInt = $pondId === '' || $pondId === null ? null : (int) $pondId;
        if ($pondIdInt !== null && !$this->canAccessPond($pondIdInt)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        // 如果关联 session，则从 session 冗余出 venue/pond，保证一致性
        $sessionIdInt = $sessionId === '' || $sessionId === null ? null : (int) $sessionId;
        if ($sessionIdInt !== null) {
            $session = FishingSession::find($sessionIdInt);
            if (!$session) {
                return json(['code' => 404, 'msg' => '开钓单不存在', 'data' => null]);
            }
            if (!$this->canAccessPond((int) $session->pond_id)) {
                return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
            }
            $venueId = (int) $session->venue_id;
            $pondIdInt = (int) $session->pond_id;
        }

        $imagesJson = is_array($images) ? json_encode($images) : (string) $images;

        $row = FishTradeLog::create([
            'session_id'   => $sessionIdInt,
            'mini_user_id' => $miniUserId === '' || $miniUserId === null ? null : (int) $miniUserId,
            'venue_id'     => $venueId,
            'pond_id'      => $pondIdInt,
            'trade_type'   => $tradeType,
            'unit'         => $unit,
            'qty'          => $qty,
            'unit_price'   => $unitPrice,
            'amount'       => $amount,
            'remark'       => $remark,
            'images'       => $imagesJson,
        ]);

        $out = $row->toArray();
        $out['images'] = is_string($out['images'] ?? null) ? (json_decode($out['images'], true) ?: []) : [];
        return json(['code' => 0, 'msg' => '添加成功', 'data' => $out]);
    }

    /**
     * 编辑 PUT /api/admin/fish-trade-logs/:id
     */
    public function update(int $id): Json
    {
        $row = FishTradeLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '交易流水不存在', 'data' => null]);
        }
        if ($row->pond_id && !$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $tradeType = $this->request->param('trade_type');
        $unit = $this->request->param('unit');
        $qty = $this->request->param('qty');
        $unitPrice = $this->request->param('unit_price');
        $amount = $this->request->param('amount');
        $remark = $this->request->param('remark');
        $images = $this->request->param('images');

        if ($tradeType !== null && $tradeType !== '') {
            if (!in_array($tradeType, ['buy_in', 'sell_out'], true)) {
                return json(['code' => 400, 'msg' => 'trade_type 仅支持 buy_in/sell_out', 'data' => null]);
            }
            $row->trade_type = $tradeType;
        }
        if ($unit !== null && $unit !== '') {
            if (!in_array($unit, ['jin', 'tiao'], true)) {
                return json(['code' => 400, 'msg' => 'unit 仅支持 jin/tiao', 'data' => null]);
            }
            $row->unit = $unit;
        }
        if ($qty !== null) $row->qty = (float) $qty;
        if ($unitPrice !== null) $row->unit_price = (float) $unitPrice;
        if ($amount !== null) $row->amount = (float) $amount;
        if ($remark !== null) $row->remark = trim((string) $remark);
        if ($images !== null) {
            $row->images = is_array($images) ? json_encode($images) : (string) $images;
        }

        $row->save();
        $out = $row->toArray();
        $out['images'] = is_string($out['images'] ?? null) ? (json_decode($out['images'], true) ?: []) : [];
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $out]);
    }

    /**
     * 删除 DELETE /api/admin/fish-trade-logs/:id
     */
    public function delete(int $id): Json
    {
        $row = FishTradeLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '交易流水不存在', 'data' => null]);
        }
        if ($row->pond_id && !$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}

