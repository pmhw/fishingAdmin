<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\PondRegion;
use app\model\PondSeat;
use think\facade\Db;
use think\response\Json;

/**
 * 钓位（座位号）管理：基于钓位区域一键生成/同步 pond_seat（用于提前制作专属二维码）
 */
class PondSeatController extends BaseController
{
    use PondScopeTrait;

    /**
     * 列表 GET /api/admin/ponds/:id/seats
     */
    public function list(int $id): Json
    {
        $pondId = $id;
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => 'pond_id 不合法', 'data' => ['list' => [], 'total' => 0]]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => ['list' => [], 'total' => 0]]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => ['list' => [], 'total' => 0]]);
        }

        $regions = PondRegion::where('pond_id', $pondId)->select();
        $regionNameMap = [];
        foreach ($regions as $r) {
            $regionNameMap[(int) $r->id] = (string) $r->name;
        }

        $rows = PondSeat::where('pond_id', $pondId)
            ->order('sort_order', 'asc')
            ->order('seat_no', 'asc')
            ->order('id', 'asc')
            ->select();

        $list = array_map(function ($row) use ($regionNameMap) {
            $arr = $row->toArray();
            $arr['region_name'] = $arr['region_id'] ? ($regionNameMap[(int) $arr['region_id']] ?? '') : '';
            return $arr;
        }, $rows->all());

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => count($list)]]);
    }

    /**
     * 一键生成/同步 POST /api/admin/ponds/:id/seats/sync
     * 规则：
     * - 基于 pond_region 的号段生成 seat_no
     * - code 默认固定：P{pond_id}-{seat_no(3位补0)}
     * - 只新增/更新归属区域；不删除历史 seat（避免已打印二维码失效）
     */
    public function sync(int $id): Json
    {
        $pondId = $id;
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => 'pond_id 不合法', 'data' => null]);
        }
        if (!FishingPond::find($pondId)) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $regions = PondRegion::where('pond_id', $pondId)
            ->order('sort_order', 'asc')
            ->order('start_no', 'asc')
            ->order('id', 'asc')
            ->select()
            ->all();

        if (empty($regions)) {
            return json(['code' => 400, 'msg' => '请先配置钓位区域（pond_region）再生成座位', 'data' => null]);
        }

        // seat_no => region_id（遇到重复 seat_no，取第一个区域并记录冲突）
        $seatToRegion = [];
        $conflicts = [];
        foreach ($regions as $rg) {
            $regionId = (int) $rg->id;
            $start = (int) $rg->start_no;
            $end   = (int) $rg->end_no;
            $min = min($start, $end);
            $max = max($start, $end);
            for ($n = $min; $n <= $max; $n++) {
                if (!isset($seatToRegion[$n])) {
                    $seatToRegion[$n] = $regionId;
                } else {
                    $conflicts[] = $n;
                }
            }
        }

        $seatNos = array_keys($seatToRegion);
        sort($seatNos);
        if (empty($seatNos)) {
            return json(['code' => 400, 'msg' => '钓位区域号段为空，无法生成', 'data' => null]);
        }
        if (count($seatNos) > 5000) {
            return json(['code' => 400, 'msg' => '座位数量过大，请缩小号段（最多 5000）', 'data' => null]);
        }

        $existingRows = PondSeat::where('pond_id', $pondId)->whereIn('seat_no', $seatNos)->select()->all();
        $existingMap = [];
        foreach ($existingRows as $r) {
            $existingMap[(int) $r->seat_no] = $r;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $toInsert = [];
        $toUpdate = [];

        foreach ($seatNos as $seatNo) {
            $regionId = (int) $seatToRegion[$seatNo];
            $desiredCode = $this->buildSeatCode($pondId, (int) $seatNo);
            $sortOrder = (int) $seatNo;
            if (!isset($existingMap[$seatNo])) {
                $toInsert[] = [
                    'pond_id'    => $pondId,
                    'region_id'  => $regionId,
                    'seat_no'    => $seatNo,
                    'code'       => $desiredCode,
                    'status'     => 'idle',
                    'sort_order' => $sortOrder,
                ];
                continue;
            }
            /** @var PondSeat $row */
            $row = $existingMap[$seatNo];
            $needSave = false;
            $data = ['id' => (int) $row->id];
            if ((int) $row->region_id !== $regionId) {
                $data['region_id'] = $regionId;
                $needSave = true;
            }
            if ((int) $row->sort_order !== $sortOrder) {
                $data['sort_order'] = $sortOrder;
                $needSave = true;
            }
            // code 一旦存在就不改（避免二维码失效）；为空时补全
            if (trim((string) $row->code) === '') {
                $data['code'] = $desiredCode;
                $needSave = true;
            }
            if ($needSave) {
                $toUpdate[] = $data;
            } else {
                $skipped++;
            }
        }

        Db::transaction(function () use ($toInsert, $toUpdate, &$created, &$updated) {
            if (!empty($toInsert)) {
                $created = PondSeat::insertAll($toInsert);
            }
            if (!empty($toUpdate)) {
                foreach ($toUpdate as $u) {
                    $id = (int) ($u['id'] ?? 0);
                    unset($u['id']);
                    if ($id > 0 && !empty($u)) {
                        PondSeat::update($u, ['id' => $id]);
                        $updated++;
                    }
                }
            }
        });

        $data = [
            'pond_id'   => $pondId,
            'total'     => count($seatNos),
            'created'   => $created,
            'updated'   => $updated,
            'skipped'   => $skipped,
            'conflicts' => array_values(array_unique($conflicts)),
        ];

        return json(['code' => 0, 'msg' => '同步完成', 'data' => $data]);
    }

    private function buildSeatCode(int $pondId, int $seatNo): string
    {
        $no = str_pad((string) $seatNo, 3, '0', STR_PAD_LEFT);
        return 'P' . $pondId . '-' . $no;
    }
}

