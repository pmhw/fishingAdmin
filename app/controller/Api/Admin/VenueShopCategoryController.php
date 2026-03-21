<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\VenueShopCategory;
use think\response\Json;

/**
 * 后台 - 钓场店铺分类（每店独立，非公共库分类）
 */
class VenueShopCategoryController extends BaseController
{
    use VenueScopeTrait;

    /**
     * 列表 GET /api/admin/shop/venues/:venue_id/categories
     */
    public function list(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场', 'data' => ['list' => [], 'total' => 0]]);
        }

        $rows = VenueShopCategory::where('venue_id', $venueId)
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
     * 新增 POST /api/admin/shop/venues/:venue_id/categories
     */
    public function create(int $venue_id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场', 'data' => null]);
        }

        $name = trim((string) $this->request->post('name', ''));
        if ($name === '') {
            return json(['code' => 400, 'msg' => '请填写分类名称', 'data' => null]);
        }

        $row = VenueShopCategory::create([
            'venue_id'   => $venueId,
            'name'       => $name,
            'sort_order' => (int) $this->request->post('sort_order', 0),
            'status'     => (int) $this->request->post('status', 1),
        ]);

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $row->toArray()]);
    }

    /**
     * 更新 PUT /api/admin/shop/venues/:venue_id/categories/:id
     */
    public function update(int $venue_id, int $id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场', 'data' => null]);
        }

        $row = VenueShopCategory::where('id', $id)->where('venue_id', $venueId)->find();
        if (!$row) {
            return json(['code' => 404, 'msg' => '分类不存在', 'data' => null]);
        }

        $name = $this->request->param('name');
        if ($name !== null && trim((string) $name) !== '') {
            $row->name = trim((string) $name);
        }
        $so = $this->request->param('sort_order');
        if ($so !== null && $so !== '') {
            $row->sort_order = (int) $so;
        }
        $st = $this->request->param('status');
        if ($st !== null && $st !== '') {
            $row->status = (int) $st;
        }
        $row->save();

        return json(['code' => 0, 'msg' => '保存成功', 'data' => $row->toArray()]);
    }

    /**
     * 删除 DELETE /api/admin/shop/venues/:venue_id/categories/:id
     */
    public function delete(int $venue_id, int $id): Json
    {
        $venueId = $venue_id;
        if (!$this->canAccessVenue($venueId)) {
            return json(['code' => 403, 'msg' => '无权管理该钓场', 'data' => null]);
        }

        $row = VenueShopCategory::where('id', $id)->where('venue_id', $venueId)->find();
        if (!$row) {
            return json(['code' => 404, 'msg' => '分类不存在', 'data' => null]);
        }
        $row->delete();

        return json(['code' => 0, 'msg' => '已删除', 'data' => null]);
    }
}
