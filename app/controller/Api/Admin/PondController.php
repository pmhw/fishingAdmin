<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\FishingVenue;
use think\response\Json;

/**
 * 池塘管理（归属钓场），支持按角色-池塘细分权限
 */
class PondController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/ponds
     * 参数：page, limit, venue_id 可选；按角色可管理范围过滤
     */
    public function list(): Json
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed !== null && empty($allowed)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $page    = (int) $this->request->get('page', 1);
        $limit   = min(max((int) $this->request->get('limit', 10), 1), 100);
        $venueId = $this->request->get('venue_id');

        $query = FishingPond::with('venue:id,name')
            ->order('sort_order', 'asc')
            ->order('id', 'desc');

        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }
        if ($venueId !== null && $venueId !== '') {
            $query->where('venue_id', (int) $venueId);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $items = array_map(function ($row) {
            $arr = $row->toArray();
            if (is_string($arr['images'] ?? null)) {
                $arr['images'] = json_decode($arr['images'], true) ?: [];
            }
            $arr['venue_name'] = $row->venue->name ?? '';
            return $arr;
        }, $paginator->items());

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $items, 'total' => $paginator->total()],
        ]);
    }

    /**
     * 详情 GET /api/admin/ponds/:id
     */
    public function detail(int $id): Json
    {
        $row = FishingPond::with('venue:id,name')->find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $data = $row->toArray();
        if (is_string($data['images'] ?? null)) {
            $data['images'] = json_decode($data['images'], true) ?: [];
        }
        $data['venue_name'] = $row->venue->name ?? '';
        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * 新增 POST /api/admin/ponds（仅「全部池塘」范围可新增）
     */
    public function create(): Json
    {
        if ($this->getAdminAllowedPondIds() !== null) {
            return json(['code' => 403, 'msg' => '仅拥有全部池塘管理权限时可新增池塘', 'data' => null]);
        }
        $data = $this->collectInput();
        if (empty($data['name'] ?? '')) {
            return json(['code' => 400, 'msg' => '池塘名称不能为空', 'data' => null]);
        }
        $venueId = (int) ($data['venue_id'] ?? 0);
        if ($venueId < 1) {
            return json(['code' => 400, 'msg' => '请选择所属钓场', 'data' => null]);
        }
        if (!FishingVenue::find($venueId)) {
            return json(['code' => 400, 'msg' => '所选钓场不存在', 'data' => null]);
        }
        $row = FishingPond::create($data);
        $row = FishingPond::with('venue:id,name')->find($row->id);
        $out = $row ? $row->toArray() : [];
        $out['venue_name'] = $row && $row->venue ? $row->venue->name : '';
        return json(['code' => 0, 'msg' => '创建成功', 'data' => $out]);
    }

    /**
     * 更新 PUT /api/admin/ponds/:id
     */
    public function update(int $id): Json
    {
        $row = FishingPond::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $data = $this->collectInput(true);
        if (!empty($data)) {
            $row->save($data);
        }
        $row = FishingPond::with('venue:id,name')->find($id);
        $out = $row->toArray();
        if (is_string($out['images'] ?? null)) {
            $out['images'] = json_decode($out['images'], true) ?: [];
        }
        $out['venue_name'] = $row->venue->name ?? '';
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $out]);
    }

    /**
     * 删除 DELETE /api/admin/ponds/:id
     */
    public function delete(int $id): Json
    {
        $row = FishingPond::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }

    private function collectInput(bool $isUpdate = false): array
    {
        $fields = [
            'venue_id', 'name', 'images', 'pond_type', 'seat_count', 'area_mu', 'water_depth',
            'fish_species', 'rod_rule', 'bait_rule', 'extra_rule', 'open_time', 'status', 'sort_order',
        ];
        $out = [];
        foreach ($fields as $f) {
            $v = $isUpdate ? $this->request->param($f) : $this->request->post($f);
            if ($v === null) {
                continue;
            }
            if ($f === 'venue_id' || $f === 'seat_count' || $f === 'sort_order') {
                $out[$f] = (int) $v;
                continue;
            }
            if ($f === 'area_mu') {
                $out[$f] = $v === '' ? null : (float) $v;
                continue;
            }
            if ($f === 'images') {
                $out[$f] = is_array($v) ? json_encode($v) : (string) $v;
                continue;
            }
            $out[$f] = $v === '' ? null : $v;
        }
        return $out;
    }
}
