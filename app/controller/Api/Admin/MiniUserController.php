<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\MiniUser;
use think\response\Json;

/**
 * 后台 - 小程序用户查询（用于手动开钓单选择用户）
 */
class MiniUserController extends BaseController
{
    /**
     * 列表 GET /api/admin/mini-users
     * 可选参数：
     * - keyword 按 昵称 / openid / 手机号 模糊搜索
     */
    public function list(): Json
    {
        $keyword = trim((string) $this->request->get('keyword', ''));
        $limit   = min(max((int) $this->request->get('limit', 20), 1), 50);

        $query = MiniUser::order('id', 'desc');
        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like) {
                $q->where('nickname', 'like', $like)
                  ->whereOr('openid', 'like', $like)
                  ->whereOr('mobile', 'like', $like);
            });
        }

        $rows = $query->limit($limit)->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id'       => (int) $row->id,
                'nickname' => (string) ($row->nickname ?? ''),
                'openid'   => (string) ($row->openid ?? ''),
                'mobile'   => (string) ($row->mobile ?? ''),
                'is_vip'   => (int) ($row->is_vip ?? 0),
                'balance'  => (float) ($row->balance ?? 0),
            ];
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }
}

