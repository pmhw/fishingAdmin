<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\PondRegion;
use think\response\Json;

/**
 * 钓位区域配置（按池塘），受池塘数据范围约束
 */
class PondRegionController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/pond-regions?pond_id=1
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
        $rows = PondRegion::where('pond_id', $pondId)
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
     * 添加 POST /api/admin/pond-regions
     * body: pond_id, name, start_no, end_no, sort_order?
     */
    public function create(): Json
    {
        $pondId = (int) $this->request->post('pond_id', 0);
        $name   = trim((string) $this->request->post('name', ''));
        $startNo = (int) $this->request->post('start_no', 0);
        $endNo   = (int) $this->request->post('end_no', 0);
        $sortOrder = (int) $this->request->post('sort_order', 0);

        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择池塘', 'data' => null]);
        }
        if ($name === '') {
            return json(['code' => 400, 'msg' => '区域名称不能为空', 'data' => null]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 400, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        if ($endNo < $startNo) {
            return json(['code' => 400, 'msg' => '结束序号不能小于起始序号', 'data' => null]);
        }

        $row = PondRegion::create([
            'pond_id'    => $pondId,
            'name'       => $name,
            'start_no'   => $startNo,
            'end_no'     => $endNo,
            'sort_order' => $sortOrder,
        ]);
        return json(['code' => 0, 'msg' => '添加成功', 'data' => $row->toArray()]);
    }

    /**
     * 删除 DELETE /api/admin/pond-regions/:id
     */
    public function delete(int $id): Json
    {
        $row = PondRegion::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '钓位区域不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
