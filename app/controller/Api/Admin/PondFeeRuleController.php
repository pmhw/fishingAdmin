<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\PondFeeRule;
use think\response\Json;

/**
 * 池塘收费规则：添加、删除、编辑
 */
class PondFeeRuleController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/pond-fee-rules?pond_id=1
     */
    public function list(): Json
    {
        $pondId = (int) $this->request->get('pond_id', 0);
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '请传入 pond_id', 'data' => ['list' => [], 'total' => 0]]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => ['list' => [], 'total' => 0]]);
        }
        $rows = PondFeeRule::where('pond_id', $pondId)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
        $list = array_map(fn ($r) => $r->toArray(), $rows->all());
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $list, 'total' => count($list)],
        ]);
    }

    /**
     * 添加 POST /api/admin/pond-fee-rules
     * body: pond_id, name, duration, amount, deposit?, sort_order?
     */
    public function create(): Json
    {
        $pondId = (int) $this->request->post('pond_id', 0);
        $name = trim((string) $this->request->post('name', ''));
        $duration = trim((string) $this->request->post('duration', ''));
        $amount = (float) $this->request->post('amount', 0);
        $deposit = (float) $this->request->post('deposit', 0);
        $sortOrder = (int) $this->request->post('sort_order', 0);

        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择池塘', 'data' => null]);
        }
        if ($name === '') {
            return json(['code' => 400, 'msg' => '收费名称不能为空', 'data' => null]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 400, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $row = PondFeeRule::create([
            'pond_id'    => $pondId,
            'name'       => $name,
            'duration'   => $duration,
            'amount'     => $amount,
            'deposit'    => $deposit,
            'sort_order' => $sortOrder,
        ]);
        return json(['code' => 0, 'msg' => '添加成功', 'data' => $row->toArray()]);
    }

    /**
     * 编辑 PUT /api/admin/pond-fee-rules/:id
     * body: name?, duration?, amount?, deposit?, sort_order?
     */
    public function update(int $id): Json
    {
        $row = PondFeeRule::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '收费规则不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $name = $this->request->param('name');
        $duration = $this->request->param('duration');
        $amount = $this->request->param('amount');
        $deposit = $this->request->param('deposit');
        $sortOrder = $this->request->param('sort_order');

        if ($name !== null) {
            $row->name = trim((string) $name);
        }
        if ($duration !== null) {
            $row->duration = trim((string) $duration);
        }
        if ($amount !== null) {
            $row->amount = (float) $amount;
        }
        if ($deposit !== null) {
            $row->deposit = (float) $deposit;
        }
        if ($sortOrder !== null) {
            $row->sort_order = (int) $sortOrder;
        }
        if ($row->name === '') {
            return json(['code' => 400, 'msg' => '收费名称不能为空', 'data' => null]);
        }
        $row->save();
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $row->toArray()]);
    }

    /**
     * 删除 DELETE /api/admin/pond-fee-rules/:id
     */
    public function delete(int $id): Json
    {
        $row = PondFeeRule::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '收费规则不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
