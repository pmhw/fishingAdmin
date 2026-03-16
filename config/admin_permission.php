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

    // ---------- 杂项：全局配置（仅增改查，禁止删除） ----------
    'GET:configs'        => 'admin.config.manage',
    'GET:configs/values' => 'admin.config.manage',
    'GET:configs/'       => 'admin.config.manage',
    'POST:configs'      => 'admin.config.manage',
    'PUT:configs/'      => 'admin.config.manage',

    // ---------- 钓场 ----------
    'GET:venues'         => 'admin.venue.manage',
    'GET:venues/'        => 'admin.venue.manage',
    'POST:venues'        => 'admin.venue.manage',
    'PUT:venues/'        => 'admin.venue.manage',
    'DELETE:venues/'      => 'admin.venue.manage',

    // ---------- 池塘 ----------
    'GET:ponds'          => 'admin.pond.manage',
    'GET:ponds/'         => 'admin.pond.manage',
    'POST:ponds'         => 'admin.pond.manage',
    'POST:ponds/'        => 'admin.pond.manage',
    'PUT:ponds/'         => 'admin.pond.manage',
    'DELETE:ponds/'      => 'admin.pond.manage',
    'GET:pond-regions'   => 'admin.pond.manage',
    'POST:pond-regions'  => 'admin.pond.manage',
    'DELETE:pond-regions/' => 'admin.pond.manage',
    'GET:pond-feed-logs'    => 'admin.pond.manage',
    'GET:pond-feed-logs/'   => 'admin.pond.manage',
    'POST:pond-feed-logs'   => 'admin.pond.manage',
    'PUT:pond-feed-logs/'   => 'admin.pond.manage',
    'DELETE:pond-feed-logs/' => 'admin.pond.manage',

    // ---------- 交易中心：订单管理（只读） ----------
    'GET:orders'         => 'admin.trade.order.manage',
    'GET:orders/'        => 'admin.trade.order.manage',

    // ---------- 经营管理：开钓/回鱼/卖鱼 ----------
    'GET:sessions'         => 'admin.biz.session.manage',
    'GET:sessions/'        => 'admin.biz.session.manage',
    'POST:sessions'        => 'admin.biz.session.manage',
    'GET:pond-return-logs'    => 'admin.biz.return.manage',
    'GET:pond-return-logs/'   => 'admin.biz.return.manage',
    'POST:pond-return-logs'   => 'admin.biz.return.manage',
    'PUT:pond-return-logs/'   => 'admin.biz.return.manage',
    'DELETE:pond-return-logs/' => 'admin.biz.return.manage',
    'GET:fish-trade-logs'    => 'admin.biz.trade.manage',
    'GET:fish-trade-logs/'   => 'admin.biz.trade.manage',
    'POST:fish-trade-logs'   => 'admin.biz.trade.manage',
    'PUT:fish-trade-logs/'   => 'admin.biz.trade.manage',
    'DELETE:fish-trade-logs/' => 'admin.biz.trade.manage',
];
