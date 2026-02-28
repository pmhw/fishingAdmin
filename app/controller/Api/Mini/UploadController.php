<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use think\response\Json;
use think\facade\Filesystem;

/**
 * 小程序端 - 通用上传（需登录，Bearer token）
 * POST /api/mini/upload  multipart/form-data 字段名 file
 * 响应：{ "url": "永久可访问的图片地址" }
 */
class UploadController extends BaseController
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_SIZE  = 2 * 1024 * 1024; // 2MB

    /**
     * 接收文件 → 存到本地 → 返回 url
     * 后续可改为存 OSS 再返回 OSS 地址。
     */
    public function index(): Json
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => '请选择文件', 'data' => null]);
        }

        $ext = strtolower($file->extension());
        if (!in_array($ext, self::IMAGE_EXT, true)) {
            return json(['code' => 400, 'msg' => '仅支持 jpg/png/gif/webp', 'data' => null]);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            return json(['code' => 400, 'msg' => '图片不能超过 2MB', 'data' => null]);
        }

        try {
            $root = config('filesystem.disks.public.root');
            if (!is_dir($root)) {
                @mkdir($root, 0755, true);
            }
            $subDir = 'upload/' . date('Ym');
            $savename = Filesystem::disk('public')->putFile($subDir, $file);
            if (!$savename) {
                return json(['code' => 500, 'msg' => '保存失败', 'data' => null]);
            }
            $url = '/storage/' . str_replace('\\', '/', $savename);
            return json(['url' => $url]);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => '上传失败：' . $e->getMessage(), 'data' => null]);
        }
    }
}
