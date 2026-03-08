<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\PondReturnRule;
use think\response\Json;

/**
 * 池塘回鱼规则：列表、添加、编辑、删除
 */
class PondReturnRuleController extends BaseController
{
    use PondScopeTrait;

    /** 回鱼方式 */
    private const RETURN_TYPES = ['jin' => '按斤', 'tiao' => '按条'];

    /**
     * 列表 GET /api/admin/pond-return-rules?pond_id=1
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
        $rows = PondReturnRule::where('pond_id', $pondId)
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
     * 添加 POST /api/admin/pond-return-rules
     * body: pond_id, name, lower_limit, upper_limit, return_type, amount, sort_order?
     */
    public function create(): Json
    {
        $pondId = (int) $this->request->post('pond_id', 0);
        $name = trim((string) $this->request->post('name', ''));
        $lowerLimit = (int) $this->request->post('lower_limit', 0);
        $upperLimit = (int) $this->request->post('upper_limit', 0);
        $returnType = trim((string) $this->request->post('return_type', 'jin'));
        $amount = (float) $this->request->post('amount', 0);
        $sortOrder = (int) $this->request->post('sort_order', 0);

        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择池塘', 'data' => null]);
        }
        if ($name === '') {
            return json(['code' => 400, 'msg' => '规则名称不能为空', 'data' => null]);
        }
        if (!array_key_exists($returnType, self::RETURN_TYPES)) {
            return json(['code' => 400, 'msg' => '回鱼方式仅支持按斤/按条', 'data' => null]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 400, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        if ($lowerLimit < 0 || $upperLimit < 0) {
            return json(['code' => 400, 'msg' => '上下限不能为负数', 'data' => null]);
        }

        $row = PondReturnRule::create([
            'pond_id'     => $pondId,
            'name'        => $name,
            'lower_limit' => $lowerLimit,
            'upper_limit' => $upperLimit,
            'return_type' => $returnType,
            'amount'      => $amount,
            'sort_order'  => $sortOrder,
        ]);
        return json(['code' => 0, 'msg' => '添加成功', 'data' => $row->toArray()]);
    }

    /**
     * 编辑 PUT /api/admin/pond-return-rules/:id
     */
    public function update(int $id): Json
    {
        $row = PondReturnRule::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '回鱼规则不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $name = $this->request->param('name');
        $lowerLimit = $this->request->param('lower_limit');
        $upperLimit = $this->request->param('upper_limit');
        $returnType = $this->request->param('return_type');
        $amount = $this->request->param('amount');
        $sortOrder = $this->request->param('sort_order');

        if ($name !== null && $name !== '') {
            $row->name = trim((string) $name);
        }
        if ($lowerLimit !== null) {
            $v = (int) $lowerLimit;
            if ($v < 0) {
                return json(['code' => 400, 'msg' => '下限不能为负数', 'data' => null]);
            }
            $row->lower_limit = $v;
        }
        if ($upperLimit !== null) {
            $v = (int) $upperLimit;
            if ($v < 0) {
                return json(['code' => 400, 'msg' => '上限不能为负数', 'data' => null]);
            }
            $row->upper_limit = $v;
        }
        if ($returnType !== null && $returnType !== '') {
            if (!array_key_exists($returnType, self::RETURN_TYPES)) {
                return json(['code' => 400, 'msg' => '回鱼方式仅支持按斤/按条', 'data' => null]);
            }
            $row->return_type = $returnType;
        }
        if ($amount !== null) {
            $row->amount = (float) $amount;
        }
        if ($sortOrder !== null) {
            $row->sort_order = (int) $sortOrder;
        }
        if ($row->name === '') {
            return json(['code' => 400, 'msg' => '规则名称不能为空', 'data' => null]);
        }
        $row->save();
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $row->toArray()]);
    }

    /**
     * 删除 DELETE /api/admin/pond-return-rules/:id
     */
    public function delete(int $id): Json
    {
        $row = PondReturnRule::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '回鱼规则不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
