<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use think\response\Json;
use think\facade\Filesystem;

/**
 * 后台通用上传（需登录）
 */
class UploadController extends BaseController
{
    /** 允许的图片扩展 */
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** 单文件最大 2MB */
    private const MAX_SIZE = 2 * 1024 * 1024;

    /**
     * 图片上传
     * POST /api/admin/upload/image  表单字段: file
     * 返回: { code, msg, data: { url } }  url 为可访问路径，如 /uploads/banner/202502/xxx.jpg
     */
    public function image(): Json
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => '请选择图片', 'data' => null]);
        }

        $ext = strtolower($file->extension());
        if (!in_array($ext, self::IMAGE_EXT, true)) {
            return json(['code' => 400, 'msg' => '仅支持 jpg/png/gif/webp', 'data' => null]);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            return json(['code' => 400, 'msg' => '图片不能超过 2MB', 'data' => null]);
        }

        try {
            $subDir = 'banner/' . date('Ym');
            $savename = Filesystem::disk('public')->putFile($subDir, $file);
            if (!$savename) {
                return json(['code' => 500, 'msg' => '保存失败', 'data' => null]);
            }
            $url = '/storage/' . str_replace('\\', '/', $savename);
            return json(['code' => 0, 'msg' => 'success', 'data' => ['url' => $url]]);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => '上传失败：' . $e->getMessage(), 'data' => null]);
        }
    }
}
