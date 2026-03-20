<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\FishingVenue;
use app\model\MiniFavoriteVenue;
use app\model\MiniUser;
use think\response\Json;
use app\controller\Api\Mini\AuthController;

/**
 * 小程序端 - 用户收藏钓场
 */
class FavoriteVenueController extends MiniBaseController
{
    /**
     * 收藏 POST /api/mini/favorites/venues
     * body: { venue_id }
     */
    public function add(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) return $error;

        $venueId = (int) $this->request->post('venue_id', 0);
        if ($venueId < 1) {
            return json(['code' => 400, 'msg' => 'venue_id 不合法', 'data' => null]);
        }
        $venue = FishingVenue::where('id', $venueId)->where('status', 1)->find();
        if (!$venue) {
            return json(['code' => 404, 'msg' => '钓场不存在或已下架', 'data' => null]);
        }

        $exists = MiniFavoriteVenue::where('mini_user_id', (int) $user->id)
            ->where('venue_id', $venueId)
            ->find();

        if ($exists) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['collected' => true]]);
        }

        MiniFavoriteVenue::create([
            'mini_user_id' => (int) $user->id,
            'venue_id' => $venueId,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => ['collected' => true]]);
    }

    /**
     * 取消收藏 DELETE /api/mini/favorites/venues/:venue_id
     */
    public function remove(int $venue_id): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) return $error;

        $venueId = (int) $venue_id;
        if ($venueId < 1) {
            return json(['code' => 400, 'msg' => 'venue_id 不合法', 'data' => null]);
        }

        MiniFavoriteVenue::where('mini_user_id', (int) $user->id)
            ->where('venue_id', $venueId)
            ->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => ['collected' => false]]);
    }

    /**
     * 收藏列表 GET /api/mini/favorites/venues?page=1&limit=10
     */
    public function list(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) return $error;

        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 50);

        $query = MiniFavoriteVenue::where('mini_user_id', (int) $user->id)
            ->order('id', 'desc');

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows = $paginator->items();

        $venueIds = [];
        foreach ($rows as $r) {
            $venueIds[] = (int) ($r->venue_id ?? 0);
        }
        $venueIds = array_values(array_filter($venueIds, static fn ($v) => $v > 0));

        $venueById = [];
        if (!empty($venueIds)) {
            $venueRows = FishingVenue::whereIn('id', $venueIds)->where('status', 1)->select();
            foreach ($venueRows as $v) {
                $arr = $v->toArray();
                $venueById[(int) ($arr['id'] ?? 0)] = $arr;
            }
        }

        $list = [];
        foreach ($rows as $r) {
            $vid = (int) ($r->venue_id ?? 0);
            $va = $venueById[$vid] ?? [];
            $list[] = [
                'id'          => $vid,
                'name'        => (string) ($va['name'] ?? ''),
                'cover_image' => (string) ($va['cover_image'] ?? ''),
                'collected'   => true,
            ];
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $list,
                'total' => (int) $paginator->total(),
            ],
        ]);
    }

    /**
     * 是否收藏：GET /api/mini/favorites/venues/:venue_id/check
     * 该接口不强制登录：未登录/token无效时返回 collected=false
     */
    public function check(int $venue_id): Json
    {
        $venueId = (int) $venue_id;
        if ($venueId < 1) {
            return json(['code' => 400, 'msg' => 'venue_id 不合法', 'data' => ['collected' => false]]);
        }

        // 可选登录：从 Authorization header 里解析 token
        $auth = (string) $this->request->header('Authorization', '');
        $token = '';
        if (str_starts_with($auth, 'Bearer ')) {
            $token = trim(substr($auth, 7));
        }

        $openid = $token !== '' ? AuthController::getOpenidByToken($token) : null;
        if (!$openid) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['collected' => false]]);
        }

        $user = MiniUser::where('openid', $openid)->find();
        if (!$user) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['collected' => false]]);
        }

        $collected = MiniFavoriteVenue::where('mini_user_id', (int) $user->id)
            ->where('venue_id', $venueId)
            ->count() > 0;

        return json(['code' => 0, 'msg' => 'success', 'data' => ['collected' => $collected ? true : false]]);
    }
}

