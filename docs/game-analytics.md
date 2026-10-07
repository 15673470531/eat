# 小游戏统计接口（砍到天亮）

## 改动与隔离

新增独立 `game_analytics_events` 表，无外键，不读写厨房、菜谱或业务用户表。`routes/api.php` 仅追加加载 `routes/game_analytics.php`。未修改原登录、权限、代理、数据库、Nginx 或环境文件。

小游戏上报无需登录；报表复用 eatWhat 的 Sanctum 管理员权限。统计路由不执行原 UpdateLastActiveAt，避免更新 eatWhat 用户活跃时间。上报与查询各有独立的限流命名空间，不占用原业务接口的限流配额。

本次完成服务端接口和建表迁移，不含可视化后台、不含线上部署。小游戏当前仍使用未启用的 wx.reportEvent 适配器，不能只开启旧开关：后续应按本文接入 wx.request，补本地玩家 UUID、UTC 事件时间、持久化待发队列及确认后删除机制。

## 部署

1. 备份数据库，部署新增文件及路由变更。
2. 在本项目 PHP 容器内执行以下命令，仅运行这一个新增迁移（不要使用 migrate:fresh/reset）：

```sh
php artisan migrate --path=database/migrations/2026_10_07_000001_create_game_analytics_events_table.php --force
php artisan config:cache
php artisan route:cache
php artisan route:list --path=game-analytics
```

若使用仓库的生产 Compose，在服务器项目目录给命令加 `docker compose -f compose.production.yml exec app` 前缀。

`GAME_ANALYTICS_ENABLED=false` 可关闭上报，返回 503；修改后重建配置缓存。默认开启服务端接收，不影响游戏端原有关闭开关。

3. 将 game.guozeshui.top 的 HTTPS 反向代理指向当前 eatWhat 服务。不要替换原域名。API 完整上报地址：`https://game.guozeshui.top/api/game-analytics/events`。在微信后台添加 request 合法域名 `https://game.guozeshui.top`。
4. 网关应限制请求体 64 KiB，并对统计路径单独限流。应用内也限制 64 KiB、每批最多 50 条、每来源每分钟最多 60 次请求。原项目信任所有代理，网关需覆盖客户端传入的 X-Forwarded-For 等转发头，且不能允许外网绕过网关访问后端，才能让 IP 限流可靠。不为统计改变全局代理配置。
5. 验证重复上报只有一条数据，匿名查询返回 401、普通用户查询返回 403、测试事件在默认报表中不可见。

未执行真实数据库迁移或部署；SQLite 内存库验证通过，生产 MySQL 上仍需执行本迁移并验收。回滚本新增迁移只删除统计表，但会删除统计数据，应备份后按部署的迁移批次精确回滚，不要全局 reset。

## POST /api/game-analytics/events

请求头 `Content-Type: application/json`、`Accept: application/json`。不放管理 Token、AppSecret 或服务器密钥到小游戏。

```json
{
  "game_id": "kdtl",
  "player_id": "8934c8ec-b475-4dcb-84c5-24a5b8f2c78d",
  "events": [{
    "run_id": "run-123",
    "seq": 1,
    "event_name": "kdtl_run_start",
    "build": "2026-10-07-a1",
    "stage": 1,
    "wave": 1,
    "weapon": "sword",
    "seconds": 0,
    "detail": "new",
    "test_device": false,
    "previous_run_id": "",
    "occurred_at": "2026-10-07T08:00:00Z"
  }]
}
```

示例时间应替换为当前事件实际时间。只接收过去 30 天至未来 10 分钟内的时间（允许轻微时钟误差）。时间格式严格 UTC、精确到秒。数据库 occurred_at/received_at 均为 UTC，独立于原应用时区。

- player_id：本地随机 UUID，不是微信 openid，不与 eatWhat 用户绑定。跨设备、清缓存后会变，不代表准确自然人数。
- run_id：1～64 位小写字母、数字、下划线或连字符；续玩保持不变。
- seq：1～10000000，同一局每个新事件递增；重试必须使用原序号。
- event_name：支持 kdtl_run_start、kdtl_run_resume、kdtl_wave_complete、kdtl_run_death、kdtl_run_complete、kdtl_settle_action、kdtl_run_leave、kdtl_run_revive、kdtl_app_hide、kdtl_app_show。
- build：最长 64 字符；weapon：最长 32 位小写字母、数字、下划线或连字符。
- stage/wave：1～10000；seconds：0～604800，模拟游戏累计秒数，不是微信停留时长。
- detail、previous_run_id 可为空；其他示例字段必填。不接收事件内的额外任意字段。

服务端用 game_id + player_id + run_id + seq 计算唯一键，重复请求不会重复计数；重复键保留首次成功入库内容。同一批出现重复键也保留第一条。整个批次先验证、再原子写入；任一事件非法则整个批次 422，不会部分入库。

成功响应：

```json
{"code":0,"data":{"acknowledged":[{"run_id":"run-123","seq":1}]}}
```

acknowledged 包含已保存和之前重复保存的记录，不表示本次新插入数量。客户端只能删除确认的事件。网络失败、429/503 应退避重试原记录；422 应诊断/隔离坏事件，避免无限阻塞整个队列。请求失败时不阻塞游戏。

其他响应：415 非 JSON；413 请求过大；429 限流；503 主动关闭。公开上报数据可以伪造，限流和校验不等于真实性认证，不得将这些数据用于发奖励或排行榜。

## 管理员查询

- `GET /api/game-analytics/summary`：窗口内事件次数、按本地 UUID 去重人数、对局数，各波完成/死亡事件、死亡原因。
- `GET /api/game-analytics/events`：分页原始事件，按接收 ID 倒序。

使用 eatWhat 已有 Sanctum Bearer Token，用户必须 `is_admin=1`；不创建新登录机制、不修改现有管理员身份。Filament 网页登录不自动等于取得 API Token，不要公开管理 Token。

公共参数：

| 参数 | 说明 |
| --- | --- |
| from / to | YYYY-MM-DD，默认上海日期最近 7 天；两端日期都包含，最多 31 天 |
| build | 精确匹配版本 |
| include_test | 默认 0 排除 test_device；1 包含测试事件 |
| event_name | 限定事件类型 |
| player_id / run_id | 排查指定玩家或对局，run_id 最好配合 player_id |
| page / per_page | 明细分页，page 最大 10000，per_page 默认 50、最大 100 |

summary 是“窗口内发生的事件”统计，不是同一批首局玩家的转化漏斗；不能把当日通关数直接除以当日新开局数。续玩、跨日和重复死亡均需区分。各事件的 players 是去重 UUID 数，events 是事件次数。客户端测试标记只能作用于上报记录，不影响微信自带数据，也不自动删除之前的正式记录。

目前没有自动清理原始事件的任务或永久汇总表，后续按实际量决定归档策略。

## 验证

使用 PHP 8.2+，项目本机可用 `/opt/homebrew/opt/php/bin/php`。在隔离副本、无真实 .env、SQLite :memory: 下执行完整测试：31 tests, 270 assertions，全通过。包含原有登录、头像、菜谱、厨房、关于页测试，以及新增接口鉴权、去重、批次原子性、字段与体积限制、独立限流、时区边界、测试数据过滤、迁移回滚隔离。
