<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use think\facade\App;
use think\Response;

/**
 * 提供 runtime/storage 下文件的访问（上传图片等）
 * GET /storage/banner/202502/xxx.jpg -> 读取 runtime/storage/banner/202502/xxx.jpg
 */
class StorageController extends BaseController
{
    public function read(string $path = ''): Response
    {
        $path = str_replace(['..', '\\'], ['', '/'], $path);
        if ($path === '') {
            return response('', 404);
        }
        $root = rtrim(App::getRuntimePath() . 'storage', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $fullPath = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $realPath = realpath($fullPath);
        $rootReal = realpath($root);
        if ($rootReal === false) {
            $rootReal = $root;
        } else {
            $rootReal = rtrim($rootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
        if ($realPath === false || !is_file($realPath) || !str_starts_with($realPath, $rootReal)) {
            return response('', 404);
        }
        return response()->file($realPath);
    }
}
