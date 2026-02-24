# fishingAdmin - ThinkPHP 8 示例 API

## 环境要求

- PHP >= 8.1
- 扩展：curl、mbstring、openssl、json

## 启动项目

```bash
# 进入项目目录
cd fishingAdmin

# 启动内置服务（默认 http://localhost:8000）
php think run
```

生产环境请将 Web 根目录指向 `public` 目录。

## 示例接口一览

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /api/index/ping | 心跳/健康检查 |
| GET | /api/index/info | API 版本信息 |
| GET | /api/user/list | 用户列表（示例数据） |
| GET | /api/user/detail/:id | 用户详情 |
| POST | /api/user/create | 创建用户（body: name, phone） |

## 请求示例

### 心跳

```bash
curl http://localhost:8000/api/index/ping
```

### 用户列表

```bash
curl http://localhost:8000/api/user/list
```

### 用户详情（id=1）

```bash
curl http://localhost:8000/api/user/detail/1
```

### 创建用户

```bash
curl -X POST http://localhost:8000/api/user/create -d "name=测试&phone=13900001111"
# 或 JSON
curl -X POST http://localhost:8000/api/user/create -H "Content-Type: application/json" -d "{\"name\":\"测试\",\"phone\":\"13900001111\"}"
```

## 响应格式

统一为 JSON，示例：

```json
{
  "code": 0,
  "msg": "success",
  "data": { ... }
}
```

- `code`: 0 成功，非 0 为错误码  
- `msg`: 提示信息  
- `data`: 业务数据  

## 项目结构（与 API 相关）

```
app/
  controller/
    Api/
      Index.php   # 通用接口：ping、info
      User.php    # 用户接口：list、detail、create
route/
  app.php        # 路由定义（含 api 分组）
```
