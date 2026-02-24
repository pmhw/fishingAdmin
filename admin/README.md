# fishingAdmin 后台（Vue 3 + Element Plus）

## 功能

- 登录（对接 ThinkPHP `/api/admin/login`）
- 管理员列表、新增、编辑、删除（需先导入 `admin_user` 表并初始化或登录）

## 开发

```bash
cd admin
npm install
npm run dev
```

浏览器访问 http://localhost:5174。默认请求会代理到 `http://127.0.0.1:8000`（需先启动 ThinkPHP：`php think run`）。

## 首次使用

1. 在数据库中执行 `md/admin_user.sql` 创建 `admin_user` 表。
2. 若表为空，可调用 `POST /api/admin/init` 创建首个管理员（如 username=admin, password=123456）。
3. 打开登录页，使用该账号登录。

## 构建

```bash
npm run build
```

产物在 `dist/`，可部署到任意静态服务器，并配置接口代理或后端 CORS。
