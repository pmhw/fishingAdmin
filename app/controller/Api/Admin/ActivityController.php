<?php
declare(strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\Activity;
use app\model\PondFeeRule;
use app\model\FishingPond;
use think\response\Json;

/**
 * 活动管理（管理端）
 *
 * MVP 接口：
 * - GET activities：列表
 * - POST activities：创建活动（draft）
 * - PUT activities/:id：编辑（draft/published 前端可控）
 * - POST activities/:id/publish：发布活动（status=published）
 * - POST activities/:id/fee-rules：为活动创建收费规则（写入 pond_fee_rule.activity_id）
 * - POST activities/:id/draw/start：开启 unified 抽号开关
 */
class ActivityController extends BaseController
{
    use PondScopeTrait;

    /**
     * GET /api/admin/activities
     * query: status?
     */
    public function list(): Json
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed !== null && empty($allowed)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $status = trim((string) $this->request->get('status', ''));
        $venueId = $this->request->get('venue_id');
        $venueId = $venueId !== null && $venueId !== '' ? (int) $venueId : 0;

        $query = Activity::order('id', 'desc');
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($venueId > 0) {
            $pondIds = FishingPond::where('venue_id', $venueId)->column('id');
            $pondIds = array_values(array_unique(array_map('intval', is_array($pondIds) ? $pondIds : [])));
            if (empty($pondIds)) {
                return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
            }
            if ($allowed !== null) {
                $pondIds = array_values(array_intersect($pondIds, $allowed));
                if (empty($pondIds)) {
                    return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
                }
            }
            $query->whereIn('pond_id', $pondIds);
        } elseif ($allowed !== null) {
            $query->whereIn('pond_id', $allowed);
        }
        $rows = $query->select();
        $list = array_map(fn ($r) => $r->toArray(), $rows->all());
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => count($list)]]);
    }

    /**
     * GET /api/admin/activities/:id
     * 含活动收费规则列表（pond_fee_rule.activity_id）
     */
    public function detail(int $id): Json
    {
        $row = Activity::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) ($row->pond_id ?? 0))) {
            return json(['code' => 403, 'msg' => '无权限查看该活动', 'data' => null]);
        }
        $activity = $row->toArray();
        $feeRules = PondFeeRule::where('activity_id', $id)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
        $activity['fee_rules'] = array_map(fn ($r) => $r->toArray(), $feeRules->all());
        return json(['code' => 0, 'msg' => 'success', 'data' => $activity]);
    }

    /**
     * POST /api/admin/activities
     * body: name, pond_id, participant_count, open_time, register_deadline, description, draw_mode, points_divisor（每1元实付可得积分；0=不发放；缺省0）, allow_balance_deduct（可选，默认1：是否允许报名会员余额抵扣及免押）
     */
    public function create(): Json
    {
        $name = trim((string) $this->request->post('name', ''));
        $pondId = (int) $this->request->post('pond_id', 0);
        $participantCount = (int) $this->request->post('participant_count', 0);
        $openTime = trim((string) $this->request->post('open_time', ''));
        $registerDeadline = trim((string) $this->request->post('register_deadline', ''));
        $description = (string) $this->request->post('description', '');
        $drawMode = trim((string) $this->request->post('draw_mode', 'random'));
        $pointsDivisor = max(0, (int) $this->request->post('points_divisor', 0));
        $allowBalanceDeduct = $this->request->post('allow_balance_deduct');
        $allowBalanceDeduct = $allowBalanceDeduct === null || $allowBalanceDeduct === ''
            ? 1
            : ((int) (bool) $allowBalanceDeduct);

        if ($name === '') {
            return json(['code' => 400, 'msg' => '活动名不能为空', 'data' => null]);
        }
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '请选择活动池塘', 'data' => null]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限使用该池塘', 'data' => null]);
        }
        if ($openTime === '' || $registerDeadline === '') {
            return json(['code' => 400, 'msg' => '开钓时间/报名截止时间不能为空', 'data' => null]);
        }
        $row = Activity::create([
            'name' => $name,
            'pond_id' => $pondId,
            'participant_count' => $participantCount,
            'open_time' => $openTime,
            'register_deadline' => $registerDeadline,
            'description' => $description,
            'status' => 'draft',
            'draw_mode' => $drawMode,
            'unified_draw_enabled' => 0,
            'points_divisor' => $pointsDivisor,
            'allow_balance_deduct' => $allowBalanceDeduct,
        ]);

        return json(['code' => 0, 'msg' => '创建成功', 'data' => $row->toArray()]);
    }

    /**
     * PUT /api/admin/activities/:id
     */
    public function update(int $id): Json
    {
        $row = Activity::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) ($row->pond_id ?? 0))) {
            return json(['code' => 403, 'msg' => '无权限编辑该活动', 'data' => null]);
        }

        $name = $this->request->param('name');
        $participantCount = $this->request->param('participant_count');
        $openTime = $this->request->param('open_time');
        $registerDeadline = $this->request->param('register_deadline');
        $description = $this->request->param('description');
        $drawMode = $this->request->param('draw_mode');
        $pointsDivisor = $this->request->param('points_divisor');
        $allowBalanceDeduct = $this->request->param('allow_balance_deduct');

        if ($name !== null && $name !== '') $row->name = trim((string) $name);
        if ($participantCount !== null) $row->participant_count = (int) $participantCount;
        if ($openTime !== null && $openTime !== '') $row->open_time = trim((string) $openTime);
        if ($registerDeadline !== null && $registerDeadline !== '') $row->register_deadline = trim((string) $registerDeadline);
        if ($description !== null) $row->description = (string) $description;
        if ($drawMode !== null && $drawMode !== '') $row->draw_mode = trim((string) $drawMode);
        if ($pointsDivisor !== null) {
            $row->points_divisor = max(0, (int) $pointsDivisor);
        }
        if ($allowBalanceDeduct !== null && $allowBalanceDeduct !== '') {
            $row->allow_balance_deduct = (int) (bool) $allowBalanceDeduct;
        }

        $row->save();
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $row->toArray()]);
    }

    /**
     * POST /api/admin/activities/:id/publish
     * 发布前须至少配置一条活动收费规则（pond_fee_rule.activity_id = 本活动）
     */
    public function publish(int $id): Json
    {
        $row = Activity::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) ($row->pond_id ?? 0))) {
            return json(['code' => 403, 'msg' => '无权限发布该活动', 'data' => null]);
        }

        $feeRuleCount = PondFeeRule::where('activity_id', $id)->count();
        if ((int) $feeRuleCount < 1) {
            return json(['code' => 400, 'msg' => '请先配置至少一条活动收费规则后再发布', 'data' => null]);
        }

        $row->status = 'published';
        $row->unified_draw_enabled = 0;
        $row->save();

        return json(['code' => 0, 'msg' => '发布成功', 'data' => $row->toArray()]);
    }

    /**
     * POST /api/admin/activities/:id/fee-rules
     * body: name, duration_value, duration_unit, amount, deposit, sort_order?
     */
    public function createFeeRule(int $id): Json
    {
        $activity = Activity::find($id);
        if (!$activity) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) ($activity->pond_id ?? 0))) {
            return json(['code' => 403, 'msg' => '无权限为该活动添加收费规则', 'data' => null]);
        }
        $pondId = (int) ($activity->pond_id ?? 0);
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => '活动池塘缺失', 'data' => null]);
        }

        $name = trim((string) $this->request->post('name', ''));
        $durationValue = $this->request->post('duration_value');
        $durationUnit = trim((string) $this->request->post('duration_unit', ''));
        $amount = (float) $this->request->post('amount', 0);
        $deposit = (float) $this->request->post('deposit', 0);
        $sortOrder = (int) $this->request->post('sort_order', 0);

        if ($name === '') {
            return json(['code' => 400, 'msg' => '收费名称不能为空', 'data' => null]);
        }
        if (!in_array($durationUnit, ['hour', 'day'], true)) {
            return json(['code' => 400, 'msg' => 'duration_unit 仅支持 hour/day', 'data' => null]);
        }
        $durationValueNum = $durationValue !== null && $durationValue !== '' ? (float) $durationValue : 0;

        $durationDisplay = $durationValueNum . ($durationUnit === 'day' ? '天' : '小时');

        $row = PondFeeRule::create([
            'pond_id' => $pondId,
            'activity_id' => $id,
            'name' => $name,
            'duration' => $durationDisplay,
            'duration_value' => $durationValueNum,
            'duration_unit' => $durationUnit,
            'amount' => $amount,
            'deposit' => $deposit,
            'sort_order' => $sortOrder,
        ]);

        return json(['code' => 0, 'msg' => '收费规则添加成功', 'data' => $row->toArray()]);
    }

    /**
     * POST /api/admin/activities/:id/draw/start
     */
    public function unifiedDrawStart(int $id): Json
    {
        $row = Activity::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) ($row->pond_id ?? 0))) {
            return json(['code' => 403, 'msg' => '无权限操作该活动', 'data' => null]);
        }
        if ((string) ($row->draw_mode ?? '') !== 'unified') {
            return json(['code' => 400, 'msg' => '仅「线上统一抽号」模式可开启此开关', 'data' => null]);
        }
        if ((string) ($row->status ?? '') !== 'published') {
            return json(['code' => 400, 'msg' => '请先发布活动后再开启抽号', 'data' => null]);
        }
        $row->unified_draw_enabled = 1;
        $row->save();
        return json(['code' => 0, 'msg' => '已开启统一抽号', 'data' => $row->toArray()]);
    }

    /**
     * POST /api/admin/activities/:id/close
     * 结束活动：小程序可再次对该池塘开钓单
     */
    public function close(int $id): Json
    {
        $row = Activity::find($id);
        if (!$row) {
            return json(['code' => 404, 'msg' => '活动不存在', 'data' => null]);
        }
        if (!$this->canAccessPond((int) ($row->pond_id ?? 0))) {
            return json(['code' => 403, 'msg' => '无权限结束该活动', 'data' => null]);
        }
        $row->status = 'closed';
        $row->unified_draw_enabled = 0;
        $row->save();
        return json(['code' => 0, 'msg' => '活动已结束', 'data' => $row->toArray()]);
    }
}

