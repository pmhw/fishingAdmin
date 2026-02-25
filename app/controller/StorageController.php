<?php
declare (strict_types = 1);

namespace app\controller;

use app\BaseController;
use think\facade\App;
use think\Response;

/**
 * 提供 runtime/storage 下文件的访问（上传图片等）
 * GET /storage/banner/202502/xxx.jpg -> 读取 runtime/storage/banner/202502/xxx.jpg
 *
 * 若仍 404：在服务器 public 目录执行 ln -s ../runtime/storage storage 后由 Web 服务器直接提供静态文件
 */
class StorageController extends BaseController
{
    public function read(string $path = ''): Response
    {
        if ($path === '') {
            $pathinfo = trim($this->request->pathinfo(), '/');
            $prefix   = 'storage/';
            $path     = str_starts_with($pathinfo, $prefix) ? substr($pathinfo, strlen($prefix)) : $pathinfo;
        }
        $path = str_replace(['..', '\\'], ['', '/'], $path);
        $path = trim($path, '/');
        if ($path === '') {
            return response('', 404);
        }
        $root     = rtrim(App::getRuntimePath() . 'storage', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
