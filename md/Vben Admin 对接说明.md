# 官方 Vben Admin 模板对接说明

本后端已提供 **Vben Admin 官方文档约定** 的登录与用户信息接口，可直接对接官方仓库克隆的项目。

---

## 一、后端已提供的 Vben 兼容接口

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/auth/login` | 登录，请求体 `username`、`password`，返回 `data.accessToken`、`data.userInfo`（含 `roles`、`realName`） |
| GET | `/api/user/info` | 获取当前用户信息，Header：`Authorization: Bearer <accessToken>` |
| GET | `/api/auth/codes` | 权限码（当前返回空数组） |

响应格式统一为：`{ code: 0, data: ..., message: '...' }`（与 Vben 文档一致）。

---

## 二、克隆并运行 Vben Admin（推荐路径无中文）

官方要求：**项目及父级目录路径不要包含中文、空格等**，否则可能报错。建议放在例如 `C:\projects\fishing-admin-vben`。

### 1. 克隆与安装

```bash
# 选一个无中文的目录
cd C:\projects
git clone https://github.com/vbenjs/vue-vben-admin.git fishing-admin-vben
cd fishing-admin-vben

# 启用 corepack 并安装依赖（需 pnpm）
corepack enable
pnpm install
```

### 2. 关闭 Mock、指向本后端

在 **应用目录** 下配置（按你用的 UI 选一个，例如 Ant Design 为 `apps/web-antd`）：

**`.env.development`**（或应用内的 env）：

```env
# 关闭 Mock，使用真实后端
VITE_USE_MOCK=false
# API 前缀，请求会发到 /api/xxx，再由代理转到 ThinkPHP
VITE_GLOB_API_URL=/api/
```

**`vite.config.mts`**（或应用内的 vite 配置）中配置代理，将 `/api` 转到本机 ThinkPHP：

```ts
server: {
  proxy: {
    '/api': {
      changeOrigin: true,
      target: 'http://127.0.0.1:8000',  // 与 php think run 一致
      ws: true,
      // 不 rewrite，请求 /api/auth/login 即转发到 http://127.0.0.1:8000/api/auth/login
    },
  },
},
```

若你使用 Nginx 等反向代理，也可把 `VITE_GLOB_API_URL` 配成后端完整地址，并保证后端允许跨域。

### 3. 登录接口路径（一般无需改）

官方模板里登录、用户信息接口通常在：

- 应用下 `src/api/core/auth` 或 `packages` 内类似路径；
- 默认已是 `POST /auth/login`、`GET /user/info` 等。

请求时会带上前缀，最终为：`/api/auth/login`、`/api/user/info`，与本后端一致，**一般不用改接口路径**。若你改了 `VITE_GLOB_API_URL` 或 baseURL，请保证最终请求到的是上述两个地址。

### 4. 启动

```bash
# 先启动 ThinkPHP（项目根目录）
cd d:\代码存储\fishingAdmin
php think run

# 再启动 Vben（在 Vben 仓库目录）
cd C:\projects\fishing-admin-vben
pnpm dev
```

按提示选择要运行的应用（如 `web-antd`），浏览器访问控制台给出的地址（如 http://localhost:5173）。登录时用已在「后台管理员」里创建好的账号（或先调 `POST /api/admin/init` 创建首个管理员）。

---

## 三、首次使用：管理员与表结构

1. **建表**：在数据库中执行 `md/admin_user.sql`，创建 `admin_user` 表。
2. **首个管理员**：若表为空，可请求 `POST /api/admin/init`（body：`username`、`password`、`nickname`）创建，或直接插入一条记录。
3. **Vben 登录**：使用该账号在 Vben 登录页输入用户名、密码即可；Token 会由 Vben 自动存并带在 `Authorization` 请求 `/api/user/info`。

---

## 四、常见问题

- **登录 401 / 404**：确认 ThinkPHP 已运行、代理 target 为 `http://127.0.0.1:8000`（或你实际端口），且请求实际到达 `/api/auth/login`。
- **CORS 报错**：开发阶段用 Vite 代理同源访问即可；若前端直连后端域名，需在 ThinkPHP 或 Nginx 配置 CORS。
- **接口返回格式**：本后端已按 Vben 约定返回 `code`、`data`、`message` 及 `accessToken`、`userInfo.roles`/`realName`，无需再改后端；若 Vben 版本对字段有差异，可在其 `request` 拦截器里做一层字段映射。

---

## 五、本仓库相关文件

- 兼容接口控制器：`app/controller/Api/VbenAuth.php`
- 路由：`route/app.php` 中 `api/auth/login`、`api/user/info`、`api/auth/codes`
- 管理员表结构：`md/admin_user.sql`
