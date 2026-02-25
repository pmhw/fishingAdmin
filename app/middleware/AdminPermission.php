<?php
declare (strict_types = 1);

namespace app\middleware;

use app\model\AdminUser;
use think\facade\Config;
use Closure;
use think\Request;
use think\Response;

/**
 * 后台接口权限校验：在 AdminAuth 之后使用，按路径+方法校验所需权限码
 * 映射配置在 config/admin_permission.php，新增功能时只需改配置文件
 */
class AdminPermission
{
    /** 无需权限校验的 path 前缀（仅登录即可） */
    private const SKIP_PATHS = ['me', 'logout'];

    private function getRoutePermissions(): array
    {
        return Config::get('admin_permission', []);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->adminId ?? null;
        if (!$adminId) {
            return $next($request);
        }

        $path = $this->normalizePath($request);
        if ($this->shouldSkipPermission($path)) {
            return $next($request);
        }

        $routePermissions = $this->getRoutePermissions();
        $required = $this->getRequiredPermission($request->method(), $path, $routePermissions);
        if ($required === null) {
            return $next($request);
        }

        $codes = $this->getAdminPermissionCodes((int) $adminId);
        if (in_array('*', $codes, true) || in_array($required, $codes, true)) {
            return $next($request);
        }

        return json(['code' => 403, 'msg' => '无权限访问', 'data' => null]);
    }

    private function normalizePath(Request $request): string
    {
        $path = $request->pathinfo();
        $path = preg_replace('#^api/?#', '', $path);
        $path = preg_replace('#^admin/?#', '', $path);
        return trim($path, '/');
    }

    private function shouldSkipPermission(string $path): bool
    {
        foreach (self::SKIP_PATHS as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    private function getRequiredPermission(string $method, string $path, array $routePermissions): ?string
    {
        $key = $method . ':' . $path;
        if (isset($routePermissions[$key])) {
            return $routePermissions[$key];
        }
        foreach ($routePermissions as $pattern => $perm) {
            if ($key === $pattern) {
                return $perm;
            }
            if (str_ends_with($pattern, '/') && str_starts_with($key, $pattern)) {
                return $perm;
            }
        }
        return null;
    }

    private function getAdminPermissionCodes(int $adminId): array
    {
        try {
            $user = AdminUser::find($adminId);
            return $user ? $user->getPermissionCodes() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
