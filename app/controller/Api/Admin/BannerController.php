<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\Banner;
use think\response\Json;

/**
 * 轮播图管理（使用已有 banner 表）
 */
class BannerController extends BaseController
{
    private function rowToApi(array $row): array
    {
        $row['image_url'] = $row['image'] ?? '';
        $row['sort']      = (int) ($row['sort_order'] ?? 0);
        return $row;
    }

    /**
     * 列表 GET /api/admin/banners
     */
    public function list(): Json
    {
        $page  = (int) $this->request->get('page', 1);
        $limit = min((int) $this->request->get('limit', 10), 100);
        $list  = Banner::order('sort_order', 'asc')->order('id', 'desc')->paginate([
            'list_rows' => $limit,
            'page'      => $page,
        ]);
        $items = array_map(fn($row) => $this->rowToApi($row->toArray()), $list->items());
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $items,
                'total' => $list->total(),
            ],
        ]);
    }

    /**
     * 详情 GET /api/admin/banners/:id
     */
    public function detail(int $id): Json
    {
        $row = Banner::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '轮播图不存在', 'data' => null]);
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $this->rowToApi($row->toArray())]);
    }

    /**
     * 新增 POST /api/admin/banners
     */
    public function create(): Json
    {
        $title    = $this->request->post('title', '');
        $imageUrl = $this->request->post('image_url', '');
        $linkUrl  = $this->request->post('link_url');
        $linkType = $this->request->post('link_type');
        $sort     = (int) $this->request->post('sort', 0);
        $status   = (int) $this->request->post('status', 1);
        $type     = $this->request->post('type', 'banner');
        if ($imageUrl === '') {
            return json(['code' => 400, 'msg' => '请填写图片地址', 'data' => null]);
        }
        $row = Banner::create([
            'type'       => $type,
            'title'      => $title,
            'image'      => $imageUrl,
            'link_url'   => $linkUrl ?: null,
            'link_type'  => $linkType ?: null,
            'sort_order' => $sort,
            'status'     => $status,
        ]);
        return json(['code' => 0, 'msg' => '创建成功', 'data' => $this->rowToApi($row->toArray())]);
    }

    /**
     * 更新 PUT /api/admin/banners/:id
     */
    public function update(int $id): Json
    {
        $row = Banner::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '轮播图不存在', 'data' => null]);
        }
        $title    = $this->request->param('title');
        $imageUrl = $this->request->param('image_url');
        $linkUrl  = $this->request->param('link_url');
        $linkType = $this->request->param('link_type');
        $sort     = $this->request->param('sort');
        $status   = $this->request->param('status');
        $type     = $this->request->param('type');
        $data     = [];
        if ($title !== null && $title !== '') {
            $data['title'] = $title;
        }
        if ($imageUrl !== null && $imageUrl !== '') {
            $data['image'] = $imageUrl;
        }
        if ($linkUrl !== null) {
            $data['link_url'] = $linkUrl === '' ? null : $linkUrl;
        }
        if ($linkType !== null) {
            $data['link_type'] = $linkType === '' ? null : $linkType;
        }
        if ($sort !== null && $sort !== '') {
            $data['sort_order'] = (int) $sort;
        }
        if ($status !== null && $status !== '') {
            $data['status'] = (int) $status;
        }
        if ($type !== null && $type !== '') {
            $data['type'] = $type;
        }
        if (!empty($data)) {
            $row->save($data);
        }
        $row = Banner::find($id);
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $this->rowToApi($row->toArray())]);
    }

    /**
     * 删除 DELETE /api/admin/banners/:id
     */
    public function delete(int $id): Json
    {
        $row = Banner::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '轮播图不存在', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
