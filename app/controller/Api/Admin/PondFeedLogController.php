<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\PondFeedLog;
use think\response\Json;

/**
 * 池塘放鱼记录：列表、添加、编辑、删除
 */
class PondFeedLogController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/pond-feed-logs?pond_id=1
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

        $rows = PondFeedLog::where('pond_id', $pondId)
            ->order('feed_time', 'desc')
            ->order('sort_order', 'asc')
            ->order('id', 'desc')
            ->select();

        $list = [];
        foreach ($rows as $r) {
            $arr = $r->toArray();
            // images 以 JSON 数组形式返回给前端
            if (is_string($arr['images'] ?? null) && $arr['images'] !== '') {
                $decoded = json_decode($arr['images'], true);
                $arr['images'] = is_array($decoded) ? $decoded : [];
            } else {
                $arr['images'] = [];
            }
            $list[] = $arr;
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $list, 'total' => count($list)],
        ]);
    }

    /**
     * 添加 POST /api/admin/pond-feed-logs
     * body: pond_id, title?, content?, images?, feed_time?, sort_order?
     * images: string[]，将保存为 JSON
     */
    public function create(): Json
    {
        $pondId = (int) $this->request->post('pond_id', 0);
        $title = trim((string) $this->request->post('title', ''));
        $content = (string) $this->request->post('content', '');
        $images = $this->request->post('images', []);
        $feedTime = $this->request->post('feed_time');
        $sortOrder = (int) $this->request->post('sort_order', 0);

        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择池塘', 'data' => null]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 400, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $imagesJson = is_array($images) ? json_encode($images) : (string) $images;

        $row = PondFeedLog::create([
            'pond_id'   => $pondId,
            'title'     => $title,
            'content'   => $content,
            'images'    => $imagesJson,
            'feed_time' => $feedTime ?: null,
            'sort_order'=> $sortOrder,
        ]);

        $out = $row->toArray();
        if (is_string($out['images'] ?? null) && $out['images'] !== '') {
            $decoded = json_decode($out['images'], true);
            $out['images'] = is_array($decoded) ? $decoded : [];
        } else {
            $out['images'] = [];
        }

        return json(['code' => 0, 'msg' => '添加成功', 'data' => $out]);
    }

    /**
     * 编辑 PUT /api/admin/pond-feed-logs/:id
     * body: title?, content?, images?, feed_time?, sort_order?
     */
    public function update(int $id): Json
    {
        $row = PondFeedLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '放鱼记录不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $title = $this->request->param('title');
        $content = $this->request->param('content');
        $images = $this->request->param('images');
        $feedTime = $this->request->param('feed_time');
        $sortOrder = $this->request->param('sort_order');

        if ($title !== null) {
            $row->title = trim((string) $title);
        }
        if ($content !== null) {
            $row->content = (string) $content;
        }
        if ($images !== null) {
            $row->images = is_array($images) ? json_encode($images) : (string) $images;
        }
        if ($feedTime !== null) {
            $row->feed_time = $feedTime === '' ? null : (string) $feedTime;
        }
        if ($sortOrder !== null) {
            $row->sort_order = (int) $sortOrder;
        }

        $row->save();

        $out = $row->toArray();
        if (is_string($out['images'] ?? null) && $out['images'] !== '') {
            $decoded = json_decode($out['images'], true);
            $out['images'] = is_array($decoded) ? $decoded : [];
        } else {
            $out['images'] = [];
        }

        return json(['code' => 0, 'msg' => '更新成功', 'data' => $out]);
    }

    /**
     * 删除 DELETE /api/admin/pond-feed-logs/:id
     */
    public function delete(int $id): Json
    {
        $row = PondFeedLog::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '放鱼记录不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) $row->pond_id)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }
        $row->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}

