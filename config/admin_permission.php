<?php
// +----------------------------------------------------------------------
// | 后台接口权限映射：路径+方法 => 权限码
// +----------------------------------------------------------------------
// | 开发新功能时：
// | 1. 在 admin_permission 表（或「角色与权限」页）增加权限记录，如 code = 'admin.xxx.manage'
// | 2. 在本数组增加对应规则，未配置的接口仅校验登录、不校验权限
// | 3. 路径为去掉 api/admin/ 后的部分，如 admin-users、roles、roles/1
// | 规则写法：'METHOD:path' 精确匹配；'METHOD:path/' 可匹配 path/123 等子路径
// +----------------------------------------------------------------------

return [
    // ---------- 管理员 ----------
    'GET:admin-users'     => 'admin.user.list',
    'GET:admin-users/'    => 'admin.user.list',
    'POST:admin-users'    => 'admin.user.create',
    'PUT:admin-users/'     => 'admin.user.update',
    'DELETE:admin-users/'  => 'admin.user.delete',

    // ---------- 角色与权限 ----------
    'GET:roles'           => 'admin.role.manage',
    'GET:roles/'          => 'admin.role.manage',
    'POST:roles'          => 'admin.role.manage',
    'PUT:roles/'          => 'admin.role.manage',
    'DELETE:roles/'       => 'admin.role.manage',
    'GET:permissions'     => 'admin.role.manage',

    // ---------- 轮播图 ----------
    'GET:banners'      => 'admin.banner.manage',
    'GET:banners/'     => 'admin.banner.manage',
    'POST:banners'     => 'admin.banner.manage',
    'PUT:banners/'     => 'admin.banner.manage',
    'DELETE:banners/'  => 'admin.banner.manage',
];
