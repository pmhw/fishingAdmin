<?php
declare (strict_types = 1);

namespace app\middleware;

use app\model\AdminUser;
use Closure;
use think\Request;
use think\Response;

/**
 * 后台接口权限校验：在 AdminAuth 之后使用，按路径+方法校验所需权限码
 */
class AdminPermission
{
    /** 路径规则 => 所需权限码（支持正则或前缀匹配） */
    private const ROUTE_PERMISSIONS = [
        // 管理员：列表、详情、增删改
        'GET:admin-users'       => 'admin.user.list',
        'GET:admin-users/'      => 'admin.user.list',
        'POST:admin-users'     => 'admin.user.create',
        'PUT:admin-users/'      => 'admin.user.update',
        'DELETE:admin-users/'   => 'admin.user.delete',
        // 角色与权限
        'GET:roles'             => 'admin.role.manage',
        'GET:roles/'            => 'admin.role.manage',
        'POST:roles'            => 'admin.role.manage',
        'PUT:roles/'            => 'admin.role.manage',
        'DELETE:roles/'         => 'admin.role.manage',
        'GET:permissions'       => 'admin.role.manage',
    ];

    /** 无需权限校验的 path 前缀（仅登录即可） */
    private const SKIP_PATHS = ['me', 'logout'];

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

        $required = $this->getRequiredPermission($request->method(), $path);
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

    private function getRequiredPermission(string $method, string $path): ?string
    {
        $key = $method . ':' . $path;
        if (isset(self::ROUTE_PERMISSIONS[$key])) {
            return self::ROUTE_PERMISSIONS[$key];
        }
        foreach (self::ROUTE_PERMISSIONS as $pattern => $perm) {
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
