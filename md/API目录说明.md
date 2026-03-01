# API 目录与文件说明

本文档描述本项目中与 API 相关的目录结构，以及每个文件夹、关键文件的作用。

---

## 一、整体结构概览

```
fishingAdmin/
├── app/
│   ├── controller/           # 控制器（含 API、存储访问）
│   │   ├── Api/              # 所有 API 控制器
│   │   │   ├── Index.php
│   │   │   ├── User.php
│   │   │   ├── VbenAuth.php
│   │   │   ├── Admin/        # 后台管理端 API
│   │   │   └── Mini/        # 小程序端 API
│   │   └── StorageController.php
│   ├── model/                # 数据模型（API 用到的表）
│   ├── middleware/           # 中间件（鉴权、权限）
│   └── ...
├── config/                   # 配置（含 API 相关）
├── route/
│   └── app.php               # 路由定义（API 路径与控制器映射）
└── md/
    └── API目录说明.md        # 本说明文档
```

---

## 二、控制器：`app/controller/`

### 1. `app/controller/`（根控制器）

| 文件 | 作用 |
|------|------|
| **StorageController.php** | 提供上传文件的访问。`GET /storage/:path` 会读取 `runtime/storage` 下的文件（如轮播图、头像），供前端或小程序展示。 |

---

### 2. `app/controller/Api/`（API 入口）

所有以 `/api/...` 开头的请求，最终会落到该目录下的控制器。

| 文件 | 作用 |
|------|------|
| **Index.php** | 通用示例接口：`/api/index/ping` 健康检查、`/api/index/info` 基础信息，不涉及业务库表。 |
| **User.php** | 示例用户接口：`/api/user/list`、`/api/user/detail/:id`、`/api/user/create`，为示例 CRUD，可与实际业务脱钩。 |
| **VbenAuth.php** | 与 Vben Admin 前端模板对接的兼容接口：`/api/auth/login`、`/api/user/info`、`/api/auth/codes`，返回格式为 code/data/message、accessToken、userInfo 等。 |

---

### 3. `app/controller/Api/Admin/`（后台管理端 API）

前缀：`/api/admin/`。供后台管理系统（Vue + Element 等）调用，需管理员登录 + 权限校验。

| 文件 | 作用 |
|------|------|
| **Auth.php** | 后台登录/登出/当前用户：`captcha` 验证码、`login` 登录、`init` 初始化、`me` 当前管理员、`logout` 登出。 |
| **UploadController.php** | 后台图片上传：`POST /api/admin/upload/image`，表单字段 `file`，保存到 `runtime/storage/banner/年月/`，返回可访问 URL。 |
| **AdminUserController.php** | 管理员账号 CRUD：列表、详情、创建、更新、删除，与角色（role_id）关联。 |
| **RoleController.php** | 角色 CRUD：列表、详情、创建、更新、删除，及角色与权限的绑定。 |
| **PermissionController.php** | 权限列表：`GET /api/admin/permissions`，供角色配置权限时使用。 |
| **BannerController.php** | 轮播图 CRUD：列表、详情、创建、更新、删除，供「内容管理 - 轮播图管理」使用。 |

---

### 4. `app/controller/Api/Mini/`（小程序端 API）

前缀：`/api/mini/`。供微信小程序调用，部分接口需小程序登录（Bearer token）。

| 文件 | 作用 |
|------|------|
| **MiniBaseController.php** | 小程序端需登录接口的基类。提供 `getCurrentUserOrFail()`：根据 token 取当前用户，失败则返回 401/404/403 的 JSON，供各接口复用。 |
| **AuthController.php** | 小程序登录：`POST /api/mini/login`，传微信 `code`，服务端 jscode2session 换 openid，创建/更新 `mini_user` 并下发 token。 |
| **UserController.php** | 小程序用户信息：`/api/mini/me`、`/api/mini/user/me`、`/api/mini/user/info` 获取当前用户；`POST/PUT /api/mini/profile`、`/api/mini/user/profile` 更新昵称、头像等。 |
| **UploadController.php** | 小程序通用上传：`POST /api/mini/upload`，multipart 字段 `file`，保存到 `runtime/storage/upload/年月/`，返回永久可访问的 `url`，供头像等使用。 |
| **BannerController.php** | 小程序轮播图列表：`GET /api/mini/banners`，返回启用状态的轮播图，无需登录。 |

---

## 三、模型：`app/model/`

与 API 直接相关的数据表模型。

| 文件 | 对应表 | 作用 |
|------|--------|------|
| **AdminUser.php** | admin_user | 后台管理员，关联角色。 |
| **AdminRole.php** | admin_role | 后台角色，关联权限。 |
| **AdminPermission.php** | admin_permission | 后台权限项（如 admin.banner.manage）。 |
| **Banner.php** | banner | 轮播图（类型、标题、图片、链接、排序、状态等）。 |
| **MiniUser.php** | mini_user | 小程序用户（openid、昵称、头像、手机号等）；接口返回时通过 `$hidden` 隐藏 openid、unionid、mobile、last_login_ip。 |
| **ContentBanner.php** | （若存在） | 若项目中有另一套轮播表可在此；当前主用 Banner。 |

---

## 四、中间件：`app/middleware/`

用于 API 鉴权与权限控制。

| 文件 | 作用 |
|------|------|
| **AdminAuth.php** | 后台 API 鉴权：校验请求中的管理员 token，解析出当前管理员并注入，未登录返回 401。 |
| **AdminPermission.php** | 后台 API 权限：在已登录基础上，按路由与配置（如 `config/admin_permission.php`）校验是否有对应权限，无权限返回 403。 |
| **MiniAuth.php** | 小程序 API 鉴权：校验 Bearer token，解析出 openid 并注入 `request->miniOpenid`，供 `getCurrentUserOrFail()` 等使用，未登录返回 401。 |

---

## 五、路由：`route/app.php`

集中定义 URL 与控制器方法的映射，API 相关主要分组如下：

| 路由前缀 | 说明 |
|----------|------|
| 无前缀 | `GET /storage/:path` → StorageController，访问上传文件。 |
| **api** | 所有 API 都在该分组下。 |
| **api/index/** | 通用示例（ping、info）。 |
| **api/user/** | 示例用户接口 + Vben 的 user/info。 |
| **api/auth/** | Vben 登录、codes。 |
| **api/mini/** | 小程序：login、banners、upload、me、user/me、user/info、profile、user/profile 等；需登录的挂 `MiniAuth`。 |
| **api/admin/** | 后台：captcha、login、init 不鉴权；其余走 `AdminAuth` + `AdminPermission`。 |

具体方法名与请求方式见 `route/app.php` 内注释与路由定义。

---

## 六、配置：`config/`（API 相关）

| 文件 | 作用 |
|------|------|
| **admin_permission.php** | 后台路由与权限码的映射（如某路由需要 `admin.banner.manage`），供 AdminPermission 中间件使用。 |
| **wechat_mini.php** | 小程序 AppID、Secret、token 有效期等，供 Mini 登录与鉴权使用。 |
| **filesystem.php** | 本地磁盘配置，`public` 盘指向 `runtime/storage`，上传接口依赖此处。 |
| **database.php** | 数据库连接，所有 API 读写都依赖。 |
| **cache.php** | 缓存配置，管理员 token、验证码、小程序 token 等可能使用。 |

---

## 七、小结

- **后台管理 API**：`app/controller/Api/Admin/` + `AdminAuth`、`AdminPermission` + `config/admin_permission.php`。
- **小程序 API**：`app/controller/Api/Mini/` + `MiniBaseController.getCurrentUserOrFail()` + `MiniAuth` + `config/wechat_mini.php`。
- **通用与示例**：`Api/Index.php`、`Api/User.php`、`Api/VbenAuth.php`。
- **上传与访问**：各端上传写 `runtime/storage`，通过 `route` 的 `GET /storage/:path` 与 `StorageController` 对外提供访问。

新增 API 时：在 `route/app.php` 增加路由，在对应 `Api/Admin` 或 `Api/Mini` 下新增或修改控制器；需登录的接口挂上对应鉴权中间件即可。
