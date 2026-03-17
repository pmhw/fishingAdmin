<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\FishingPond;
use app\model\FishingSession;
use app\model\PondRegion;
use app\model\PondSeat;
use app\service\WxMiniCodeService;
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

        // 当前池塘下进行中开钓单占用的钓位 id 列表
        $occupiedSeatIds = FishingSession::where('pond_id', $pondId)
            ->where('status', 'ongoing')
            ->whereNotNull('seat_id')
            ->where('seat_id', '>', 0)
            ->column('seat_id');
        $occupiedSeatIds = array_flip(array_map('intval', is_array($occupiedSeatIds) ? $occupiedSeatIds : []));

        $list = array_map(function ($row) use ($regionNameMap) {
            $arr = $row->toArray();
            $arr['region_name'] = $arr['region_id'] ? ($regionNameMap[(int) $arr['region_id']] ?? '') : '';
            return $arr;
        }, $rows->all());

        // 添加 occupied 字段：是否被占用（存在进行中开钓单）
        foreach ($list as &$item) {
            $sid = (int) ($item['id'] ?? 0);
            $item['occupied'] = $sid > 0 && isset($occupiedSeatIds[$sid]);
        }
        unset($item);

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => count($list)]]);
    }

    /**
     * 批量生成座位小程序码（二维码图片）
     * POST /api/admin/ponds/:id/seats/qrcodes
     *
     * body:
     * - page (可选，默认 pages/session-open/index)
     * - env_version (可选 trial|release，默认 trial)
     *
     * scene 建议尽量短：v=venue_id&p=pond_id&s=seat_id
     */
    public function generateQrcodes(int $id): Json
    {
        $pondId = $id;
        if ($pondId < 1) {
            return json(['code' => 400, 'msg' => 'pond_id 不合法', 'data' => null]);
        }
        /** @var FishingPond|null $pond */
        $pond = FishingPond::find($pondId);
        if (!$pond) {
            return json(['code' => 404, 'msg' => '池塘不存在', 'data' => null]);
        }
        if (!$this->canAccessPond($pondId)) {
            return json(['code' => 403, 'msg' => '无权限管理该池塘', 'data' => null]);
        }

        $page = trim((string) $this->request->param('page', 'pages/session-open/index'));
        if ($page === '') $page = 'pages/session-open/index';
        $envVersion = (string) $this->request->param('env_version', 'trial');

        $seats = PondSeat::where('pond_id', $pondId)->order('seat_no', 'asc')->select()->all();
        if (empty($seats)) {
            return json(['code' => 400, 'msg' => '该池塘暂无钓位，请先生成座位', 'data' => null]);
        }

        $venueId = (int) ($pond->venue_id ?? 0);
        $subDir = date('Ym') . '/' . date('d');
        $baseDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'seat_qr' . DIRECTORY_SEPARATOR . $pondId . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            return json(['code' => 500, 'msg' => '创建二维码目录失败', 'data' => null]);
        }

        $list = [];
        $fail = 0;
        foreach ($seats as $seat) {
            $sid = (int) $seat->id;
            if ($sid < 1) continue;

            // scene 长度限制 32：使用短 key
            $scene = 'v=' . $venueId . '&p=' . $pondId . '&s=' . $sid;

            [$png, $err] = WxMiniCodeService::getUnlimitedCode($page, $scene, $envVersion, 430);
            if (!$png) {
                $fail++;
                $list[] = [
                    'seat_id' => $sid,
                    'seat_no' => (int) ($seat->seat_no ?? 0),
                    'code'    => (string) ($seat->code ?? ''),
                    'scene'   => $scene,
                    'qr_url'  => null,
                    'error'   => $err,
                ];
                continue;
            }

            $fileName = 'seat_' . $sid . '.png';
            $filePath = $baseDir . DIRECTORY_SEPARATOR . $fileName;
            @file_put_contents($filePath, $png);
            $qrUrl = '/storage/seat_qr/' . $pondId . '/' . $subDir . '/' . $fileName;

            $list[] = [
                'seat_id' => $sid,
                'seat_no' => (int) ($seat->seat_no ?? 0),
                'code'    => (string) ($seat->code ?? ''),
                'scene'   => $scene,
                'qr_url'  => $qrUrl,
                'error'   => null,
            ];
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'pond_id' => $pondId,
                'page'    => $page,
                'env_version' => $envVersion === 'release' ? 'release' : 'trial',
                'total'   => count($list),
                'fail'    => $fail,
                'list'    => $list,
            ],
        ]);
    }

    /**
     * 打包下载：将该池塘已生成的座位二维码打包为 zip
     * POST /api/admin/ponds/:id/seats/qrcodes/zip
     */
    public function downloadQrcodesZip(int $id): Json
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

        $srcDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'seat_qr' . DIRECTORY_SEPARATOR . $pondId;
        if (!is_dir($srcDir)) {
            return json(['code' => 400, 'msg' => '该池塘尚未生成二维码，请先生成', 'data' => null]);
        }

        $zipDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'seat_qr_zip' . DIRECTORY_SEPARATOR . $pondId;
        if (!is_dir($zipDir) && !@mkdir($zipDir, 0777, true) && !is_dir($zipDir)) {
            return json(['code' => 500, 'msg' => '创建 zip 目录失败', 'data' => null]);
        }

        $zipName = 'seat_qr_pond_' . $pondId . '_' . date('Ymd_His') . '.zip';
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return json(['code' => 500, 'msg' => '创建 zip 文件失败', 'data' => null]);
        }

        $fileCount = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) continue;
            $path = $file->getPathname();
            if (strtolower($file->getExtension()) !== 'png') continue;
            $localName = ltrim(str_replace($srcDir, '', $path), DIRECTORY_SEPARATOR);
            $zip->addFile($path, $localName);
            $fileCount++;
        }
        $zip->close();

        if ($fileCount <= 0) {
            @unlink($zipPath);
            return json(['code' => 400, 'msg' => '未找到可打包的二维码图片', 'data' => null]);
        }

        $zipUrl = '/storage/seat_qr_zip/' . $pondId . '/' . $zipName;
        return json(['code' => 0, 'msg' => 'success', 'data' => ['zip_url' => $zipUrl, 'files' => $fileCount]]);
    }

    /**
     * 清理：删除该池塘已生成的二维码图片与 zip 包
     * DELETE /api/admin/ponds/:id/seats/qrcodes/cleanup
     */
    public function cleanupQrcodes(int $id): Json
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

        $qrDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'seat_qr' . DIRECTORY_SEPARATOR . $pondId;
        $zipDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'seat_qr_zip' . DIRECTORY_SEPARATOR . $pondId;

        $deleted = [
            'qr_deleted' => $this->deleteDirRecursive($qrDir),
            'zip_deleted' => $this->deleteDirRecursive($zipDir),
        ];

        return json(['code' => 0, 'msg' => '清理完成', 'data' => $deleted]);
    }

    private function deleteDirRecursive(string $dir): bool
    {
        if ($dir === '' || !file_exists($dir)) {
            return true;
        }
        if (is_file($dir) || is_link($dir)) {
            return @unlink($dir);
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        return @rmdir($dir);
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

