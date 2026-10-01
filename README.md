# eatWhat 后端

选菜小程序后端。由通用骨架 `laravelBase` 复制而来（Laravel 12 + Filament 5 + Sanctum，全 Docker）。

## 端口

| 服务 | 端口 |
| --- | --- |
| nginx | 8087 / 8447 |
| MySQL | 3311 |
| phpMyAdmin | 18085 |
| adminer | 18086 |

## 本地启动

```
docker compose up -d
docker compose exec app php artisan migrate
```

`.env` 已生成（含 APP_KEY）。后台：http://localhost:8087/admin

## 当前状态

只有通用骨架，没有任何业务表和业务接口：

- 微信登录 `/api/user/login`（本地 `code=dev_001` 免校验直接登录）、`/api/user/info`、`/api/user/update-name`、`/api/user/bind-phone`、`/api/user/logout`
- Filament 后台 + 用户管理
- migration 只有 9 个通用表（users/cache/jobs/personal_access_tokens + users 的 openid/is_admin/phone/last_login_at/last_active_at）

前端 `WeChatProjects/eatWhat`，菜品数据目前放在前端 `data/` 下（静态 JSON，零请求）。

## 待确认（方案定了再做）

- 菜品/食材是否需要入库 + 接口下发（好处是改菜不用发版，代价是要加表和接口）
- 用户侧数据（我的冰箱、收藏、历史记录）是否需要建表

## 注意

- `.env` 的 `WECHAT_APPID` / `WECHAT_APPSECRET` 还是空的，填自己的小程序账号，
  **不要沿用 money 的**（骨架里原本硬编码了 money 的 appid，已改成走 env）
- 上线前改 `docker/nginx/default.conf` 的域名与证书文件名（模板里是 `example.com`）
- 本地开发走 `docker-compose.override.yml`，连的是本项目自己的 mysql 容器，不会碰线上库
# eat
