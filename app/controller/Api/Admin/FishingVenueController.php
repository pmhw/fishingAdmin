<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingVenue;
use think\response\Json;

/**
 * 钓场管理
 */
class FishingVenueController extends BaseController
{
    /**
     * 列表 GET /api/admin/venues
     */
    public function list(): Json
    {
        $page  = (int) $this->request->get('page', 1);
        $limit = min((int) $this->request->get('limit', 10), 100);
        $status = $this->request->get('status');
        $query = FishingVenue::order('sort_order', 'asc')->order('id', 'desc');
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $list = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $items = array_map(function ($row) {
            $arr = $row->toArray();
            if (is_string($arr['images'] ?? null)) {
                $arr['images'] = json_decode($arr['images'], true) ?: [];
            }
            return $arr;
        }, $list->items());
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $items, 'total' => $list->total()],
        ]);
    }

    /**
     * 详情 GET /api/admin/venues/:id
     */
    public function detail(int $id): Json
    {
        $row = FishingVenue::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        $data = $row->toArray();
        if (is_string($data['images'] ?? null)) {
            $data['images'] = json_decode($data['images'], true) ?: [];
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * 新增 POST /api/admin/venues
     */
    public function create(): Json
    {
        $data = $this->collectVenueInput();
        if (empty($data['name'] ?? '')) {
            return json(['code' => 400, 'msg' => '钓场名称不能为空', 'data' => null]);
        }
        $row = FishingVenue::create($data);
        return json(['code' => 0, 'msg' => '创建成功', 'data' => $row->toArray()]);
    }

    /**
     * 更新 PUT /api/admin/venues/:id
     */
    public function update(int $id): Json
    {
        $row = FishingVenue::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        $data = $this->collectVenueInput(true);
        if (!empty($data)) {
            $row->save($data);
        }
        $row = FishingVenue::find($id);
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $row->toArray()]);
    }

    /**
     * 显示/隐藏 PUT /api/admin/venues/:id/status  body: status (0=隐藏 1=显示)
     */
    public function updateStatus(int $id): Json
    {
        $row = FishingVenue::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        $status = (int) $this->request->param('status', 1);
        if (!in_array($status, [0, 1, 2], true)) {
            return json(['code' => 400, 'msg' => '状态值无效', 'data' => null]);
        }
        $row->save(['status' => $status]);
        return json(['code' => 0, 'msg' => '操作成功', 'data' => $row->toArray()]);
    }

    /**
     * 删除 DELETE /api/admin/venues/:id
     */
    public function delete(int $id): Json
    {
        $row = FishingVenue::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '钓场不存在', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }

    private function collectVenueInput(bool $isUpdate = false): array
    {
        $out = [];
        $fields = [
            'name', 'intro', 'description', 'cover_image', 'province', 'city', 'district', 'address',
            'longitude', 'latitude', 'contact_phone', 'contact_wechat', 'opening_hours',
            'price_type', 'price_info', 'price_min', 'price_max', 'facilities', 'fish_species',
            'status', 'sort_order',
        ];
        foreach ($fields as $f) {
            $v = $isUpdate ? $this->request->param($f) : $this->request->post($f);
            if ($v === null) {
                continue;
            }
            if ($f === 'images') {
                continue;
            }
            if (in_array($f, ['longitude', 'latitude', 'price_min', 'price_max'], true)) {
                $out[$f] = $v === '' ? null : (float) $v;
                continue;
            }
            if ($f === 'status' || $f === 'sort_order') {
                $out[$f] = (int) $v;
                continue;
            }
            $out[$f] = $v === '' ? null : $v;
        }
        $images = $isUpdate ? $this->request->param('images') : $this->request->post('images');
        if ($images !== null) {
            $out['images'] = is_array($images) ? json_encode($images) : $images;
        }
        return $out;
    }
}
