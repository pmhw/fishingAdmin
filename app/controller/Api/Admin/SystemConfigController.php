<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\SystemConfig;
use think\response\Json;

/**
 * 全局配置（Key-Value）
 * 仅支持列表、详情、新增、更新；禁止删除，删除需在数据库中操作。
 */
class SystemConfigController extends BaseController
{
    /**
     * 列表 GET /api/admin/configs
     */
    public function list(): Json
    {
        $page  = (int) $this->request->get('page', 1);
        $limit = min((int) $this->request->get('limit', 10), 100);
        $list  = SystemConfig::order('id', 'asc')->paginate([
            'list_rows' => $limit,
            'page'      => $page,
        ]);
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $list->items(),
                'total' => $list->total(),
            ],
        ]);
    }

    /**
     * 详情 GET /api/admin/configs/:id
     */
    public function detail(int $id): Json
    {
        $row = SystemConfig::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '配置不存在', 'data' => null]);
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $row->toArray()]);
    }

    /**
     * 新增 POST /api/admin/configs
     * body: config_key, config_value?, remark?
     */
    public function create(): Json
    {
        $key   = $this->request->post('config_key', '');
        $value = $this->request->post('config_value', '');
        $remark = $this->request->post('remark', '');
        $key   = is_string($key) ? trim($key) : '';
        if ($key === '') {
            return json(['code' => 400, 'msg' => '变量名(key)不能为空', 'data' => null]);
        }
        if (SystemConfig::where('config_key', $key)->find()) {
            return json(['code' => 400, 'msg' => '该变量名已存在', 'data' => null]);
        }
        $row = SystemConfig::create([
            'config_key'   => $key,
            'config_value' => $value,
            'remark'       => $remark === '' ? null : $remark,
        ]);
        return json(['code' => 0, 'msg' => '创建成功', 'data' => $row->toArray()]);
    }

    /**
     * 更新 PUT /api/admin/configs/:id
     * body: config_value?, remark?（不可修改 config_key）
     */
    public function update(int $id): Json
    {
        $row = SystemConfig::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '配置不存在', 'data' => null]);
        }
        $value  = $this->request->param('config_value');
        $remark = $this->request->param('remark');
        $data   = [];
        if ($value !== null) {
            $data['config_value'] = $value;
        }
        if ($remark !== null) {
            $data['remark'] = $remark === '' ? null : $remark;
        }
        if (!empty($data)) {
            $row->save($data);
        }
        $row = SystemConfig::find($id);
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $row->toArray()]);
    }
}
