<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\SystemConfig;
use think\facade\Db;
use think\response\Json;

/**
 * 支付配置（写入 system_config）
 */
class PaymentConfigController extends BaseController
{
    /**
     * 获取支付相关配置（按约定 keys）
     * GET /api/admin/payment-config
     */
    public function show(): Json
    {
        $keys = [
            // 微信支付 v2（小程序支付）
            'pay_notify_url',
            'pay_key', // APIv2 key
            'pay_mch_id',
            // 小程序
            'mini_appid',
            'mini_secret',
            // 微信 v3 转账
            'wxpay_v3_serial_no',
            'wxpay_v3_private_key_pem',
            'wxpay_v3_appid',
            'wxpay_v3_transfer_notify_url',
            'wxpay_v3_transfer_scene_id',
        ];

        $list = SystemConfig::whereIn('config_key', $keys)->column('config_value', 'config_key');
        $data = [];
        foreach ($keys as $k) {
            $data[$k] = (string) ($list[$k] ?? '');
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => (object) $data]);
    }

    /**
     * 保存支付相关配置（批量 upsert 到 system_config）
     * PUT /api/admin/payment-config
     *
     * body: {
     *   pay_notify_url?, pay_key?, pay_mch_id?, mini_appid?, mini_secret?,
     *   wxpay_v3_serial_no?, wxpay_v3_private_key_pem?, wxpay_v3_appid?,
     *   wxpay_v3_transfer_notify_url?, wxpay_v3_transfer_scene_id?
     * }
     */
    public function save(): Json
    {
        $payload = $this->request->param();
        if (!is_array($payload)) {
            return json(['code' => 400, 'msg' => '参数错误', 'data' => null]);
        }

        $allowKeys = [
            'pay_notify_url'              => '微信支付回调地址',
            'pay_key'                     => '微信支付 APIv2 密钥',
            'pay_mch_id'                  => '微信支付商户号',
            'mini_appid'                  => '小程序 AppID',
            'mini_secret'                 => '小程序 AppSecret',
            'wxpay_v3_serial_no'          => '微信支付 v3 商户证书序列号',
            'wxpay_v3_private_key_pem'    => '微信支付 v3 商户私钥 PEM（PKCS#8）',
            'wxpay_v3_appid'              => '微信支付 v3 转账小程序 AppID',
            'wxpay_v3_transfer_notify_url'=> '微信 v3 转账回调地址',
            'wxpay_v3_transfer_scene_id'  => '微信 v3 转账场景 ID',
        ];

        $dataToSave = [];
        foreach ($allowKeys as $k => $remark) {
            if (array_key_exists($k, $payload)) {
                $val = $payload[$k];
                $dataToSave[$k] = [
                    'value'  => is_string($val) ? $val : (is_numeric($val) ? (string) $val : ''),
                    'remark' => $remark,
                ];
            }
        }

        if (empty($dataToSave)) {
            return json(['code' => 400, 'msg' => '未提供可保存的字段', 'data' => null]);
        }

        Db::transaction(function () use ($dataToSave) {
            foreach ($dataToSave as $key => $cfg) {
                $row = SystemConfig::where('config_key', $key)->find();
                if ($row) {
                    $row->save([
                        'config_value' => $cfg['value'],
                        'remark'       => $cfg['remark'],
                    ]);
                } else {
                    SystemConfig::create([
                        'config_key'   => $key,
                        'config_value' => $cfg['value'],
                        'remark'       => $cfg['remark'],
                    ]);
                }
            }
        });

        return $this->show();
    }
}

