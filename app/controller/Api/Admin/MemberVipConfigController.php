<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\service\MemberBalanceRechargeService;
use think\response\Json;

/**
 * 会员充值档位 + 单笔满额自动升级 VIP（system_config）
 */
class MemberVipConfigController extends BaseController
{
    /**
     * GET /api/admin/member-vip-settings
     */
    public function show(): Json
    {
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => MemberBalanceRechargeService::getAdminPayload(),
        ]);
    }

    /**
     * PUT /api/admin/member-vip-settings
     * body: { "packages_yuan": [100,200], "vip_upgrade_threshold_yuan": 500 }
     */
    public function update(): Json
    {
        $packages = $this->request->param('packages_yuan');
        if (!is_array($packages)) {
            return json(['code' => 400, 'msg' => 'packages_yuan 须为数组', 'data' => null]);
        }
        $thresholdRaw = $this->request->param('vip_upgrade_threshold_yuan', 0);
        if ($thresholdRaw !== null && $thresholdRaw !== '' && !is_numeric($thresholdRaw)) {
            return json(['code' => 400, 'msg' => 'vip_upgrade_threshold_yuan 格式错误', 'data' => null]);
        }
        $threshold = (float) $thresholdRaw;

        $floatPackages = [];
        foreach ($packages as $p) {
            if (is_numeric($p)) {
                $floatPackages[] = (float) $p;
            }
        }

        try {
            MemberBalanceRechargeService::saveAdminPayload($floatPackages, $threshold);
        } catch (\InvalidArgumentException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage(), 'data' => null]);
        }

        return json([
            'code' => 0,
            'msg'  => '保存成功',
            'data' => MemberBalanceRechargeService::getAdminPayload(),
        ]);
    }
}
