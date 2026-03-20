<?php
declare(strict_types=1);

namespace app\controller\Api\Mini;

use app\model\MiniUser;
use app\model\SystemConfig;
use app\service\WxMiniCodeService;
use think\response\Json;

/**
 * 小程序会员码：动态二维码（timestamp + 签名 + 有效期校验）
 *
 * 设计目标：
 * - 二维码生成后不需要落库即可校验防篡改（HMAC签名）
 * - 带 timestamp，有效期过了就判无效（防截图长期有效）
 */
class VipCodeController extends MiniBaseController
{
    private const DEFAULT_TTL_SECONDS = 300; // 默认 5 分钟
    private const SIG_TRUNC_LEN = 10; // HMAC截断长度（十六进制字符数）
    private const SCENE_MAX_BYTES = 32; // 微信小程序码 scene 限制（以字节计，尽量短）

    /**
     * 生成会员码二维码图片
     * POST /api/mini/vip/codes
     *
     * body:
     * - page: 小程序页面路径（默认 pages/vip/index）
     * - env_version: trial|release（默认 trial）
     *
     * 返回：
     * - scene（用于小程序 onLoad/options.scene 解析）
     * - mini_qr_url（二维码图片相对路径，前端用 image 展示）
     * - expires_at / ttl_seconds
     */
    public function create(): Json
    {
        [$user, $error] = $this->getCurrentUserOrFail();
        if ($error !== null) return $error;

        /** @var MiniUser $user */
        $miniUserId = (int) $user->id;
        if ($miniUserId < 1) {
            return json(['code' => 400, 'msg' => '用户id无效', 'data' => null]);
        }

        $page = trim((string) $this->request->post('page', 'pages/vip/index'));
        if ($page === '') $page = 'pages/vip/index';

        $envVersion = (string) $this->request->post('env_version', 'trial');
        $envVersion = $envVersion === 'release' ? 'release' : 'trial';

        $secret = (string) SystemConfig::getValue('vip_code_secret', '');
        if ($secret === '') {
            return json(['code' => 500, 'msg' => '系统缺少 vip_code_secret 配置', 'data' => null]);
        }

        $ttlSeconds = (int) SystemConfig::getValue('vip_code_ttl_seconds', self::DEFAULT_TTL_SECONDS);
        if ($ttlSeconds <= 0) $ttlSeconds = self::DEFAULT_TTL_SECONDS;
        // 兜底：限制范围，避免 ttl 太大导致截图意义变小
        if ($ttlSeconds < 60) $ttlSeconds = 60;
        if ($ttlSeconds > 3600) $ttlSeconds = 3600;

        $now = time();
        // 用“有效期桶”保证动态刷新：在同一桶内二维码可复用，跨桶生成新码
        $bucketTs = (int) (floor($now / $ttlSeconds) * $ttlSeconds);
        $expiresAt = date('Y-m-d H:i:s', $bucketTs + $ttlSeconds);

        [$scene, $sig] = $this->buildScene($miniUserId, $bucketTs, $secret);
        if ($scene === null) {
            return json(['code' => 500, 'msg' => 'scene 生成失败（长度可能超限）', 'data' => null]);
        }

        // 缓存落盘：同一个用户+同一个桶+同一个环境，不重复调用微信接口
        $subDir = $envVersion . DIRECTORY_SEPARATOR . 'uid_' . $miniUserId . DIRECTORY_SEPARATOR . date('Ym', $bucketTs) . '/' . date('d', $bucketTs);
        $baseDir = public_path() . 'storage' . DIRECTORY_SEPARATOR . 'vip_qr' . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            return json(['code' => 500, 'msg' => '创建二维码目录失败', 'data' => null]);
        }

        $fileName = 'vip_' . $miniUserId . '_' . $bucketTs . '_' . $sig . '.png';
        $filePath = $baseDir . DIRECTORY_SEPARATOR . $fileName;
        $qrUrl = '/storage/vip_qr/' . $subDir . '/' . rawurlencode($fileName);

        if (!is_file($filePath)) {
            [$png, $err] = WxMiniCodeService::getUnlimitedCode($page, $scene, $envVersion, 430);
            if (!$png) {
                return json(['code' => 500, 'msg' => '生成小程序会员码失败：' . ($err ?? 'unknown'), 'data' => null]);
            }
            @file_put_contents($filePath, $png);
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'scene' => $scene,
                'ttl_seconds' => $ttlSeconds,
                'expires_at' => $expiresAt,
                'env_version' => $envVersion,
                'mini_qr_url' => $qrUrl,
                // 便于前端/日志调试
                'ts' => $bucketTs,
            ],
        ]);
    }

    /**
     * 校验会员码是否有效（签名 + timestamp过期）
     * GET /api/mini/vip/codes/verify
     *
     * 参数：
     * - scene: 从 options.scene 解析出来的 scene 字符串（推荐）
     * - 或 uid, ts, sig：作为兜底
     */
    public function verify(): Json
    {
        $scene = trim((string) $this->request->get('scene', ''));
        $uid = (int) $this->request->get('uid', 0);
        $ts = (int) $this->request->get('ts', 0);
        $sig = trim((string) $this->request->get('sig', ''));

        $secret = (string) SystemConfig::getValue('vip_code_secret', '');
        if ($secret === '') {
            return json(['code' => 500, 'msg' => '系统缺少 vip_code_secret 配置', 'data' => null]);
        }

        $ttlSeconds = (int) SystemConfig::getValue('vip_code_ttl_seconds', self::DEFAULT_TTL_SECONDS);
        if ($ttlSeconds <= 0) $ttlSeconds = self::DEFAULT_TTL_SECONDS;
        if ($ttlSeconds < 60) $ttlSeconds = 60;
        if ($ttlSeconds > 3600) $ttlSeconds = 3600;

        if ($scene !== '') {
            $scene = urldecode($scene);
            $parsed = $this->parseScene($scene);
            if ($parsed === null) {
                return json(['code' => 400, 'msg' => 'scene 格式错误', 'data' => ['valid' => false]]);
            }
            $uid = (int) $parsed['uid'];
            $ts = (int) $parsed['ts'];
            $sig = (string) $parsed['sig'];
        }

        if ($uid < 1 || $ts < 1 || $sig === '') {
            return json(['code' => 400, 'msg' => '参数缺失', 'data' => ['valid' => false]]);
        }

        $now = time();

        // 先校验签名：签名正确才允许返回余额等敏感信息
        $calcSig = $this->calcSig($uid, $ts, $secret);
        $sigOk = strcasecmp($calcSig, $sig) === 0;
        if (!$sigOk) {
            return json(['code' => 200, 'msg' => 'success', 'data' => ['valid' => false, 'reason' => 'invalid_signature']]);
        }

        /** @var MiniUser|null $user */
        $user = MiniUser::find($uid);
        $isVip = $user ? (int) ($user->is_vip ?? 0) === 1 : false;
        $nickname = $user ? (string) ($user->nickname ?? '') : '';
        $balance = (string) number_format((float) ($user ? ($user->balance ?? 0) : 0), 2, '.', '');

        $reason = null;
        $valid = true;
        if ($ts > $now) {
            $valid = false;
            $reason = 'invalid_timestamp';
        } else {
            $expired = ($now - $ts) > $ttlSeconds;
            if ($expired) {
                $valid = false;
                $reason = 'expired';
            }
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'valid' => $valid,
                'uid' => $uid,
                'ts' => $ts,
                'sig' => $sig,
                'ttl_seconds' => $ttlSeconds,
                'is_vip' => $isVip ? 1 : 0,
                'nickname' => $nickname,
                'balance' => $balance,
                'reason' => $valid ? null : $reason,
            ],
        ]);
    }

    /**
     * 构造 scene：uid_ts_sig（用下划线分隔）
     * @return array{0:string|null,1:string|null}
     */
    private function buildScene(int $uid, int $ts, string $secret): array
    {
        $sig = $this->calcSig($uid, $ts, $secret);
        $scene = $uid . '_' . $ts . '_' . $sig;
        // 避免 scene 超出限制（按字节粗略判断）
        if (strlen($scene) > self::SCENE_MAX_BYTES) {
            return [null, null];
        }
        return [$scene, $sig];
    }

    private function calcSig(int $uid, int $ts, string $secret): string
    {
        $payload = $uid . '|' . $ts;
        $hmac = hash_hmac('sha256', $payload, $secret);
        return substr(strtolower($hmac), 0, self::SIG_TRUNC_LEN);
    }

    /**
     * 解析 scene：uid_ts_sig
     * @return array{uid:int,ts:int,sig:string}|null
     */
    private function parseScene(string $scene): ?array
    {
        $parts = explode('_', $scene);
        if (count($parts) !== 3) return null;
        [$uidStr, $tsStr, $sigStr] = $parts;
        if ($uidStr === '' || $tsStr === '' || $sigStr === '') return null;
        if (!ctype_digit($uidStr) || !ctype_digit($tsStr)) return null;
        return [
            'uid' => (int) $uidStr,
            'ts' => (int) $tsStr,
            'sig' => (string) $sigStr,
        ];
    }
}

