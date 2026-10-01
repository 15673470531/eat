# 数据入库与本地联调

## 数据表

- `ingredients`：食材、分类、别名与排序（98 种）。
- `recipes`：菜名与排序；`details` JSON 保存做法步骤、菜品分类、标签、人数、耗时、提示等随菜谱整体读取的内容（44 道）。
- `recipe_materials`：菜谱关联食材、主料/必需调料/可选材料、数量、单位、省略说明。
- `app_settings`：关于我们的品牌名、标语、邮箱、开发者微信。
- `users` / `personal_access_tokens`：现有微信用户与登录凭证。
- `user_kitchens`：每用户一个聚合版本号；偏好 JSON 包含人数、临时选择、常备调料、快手/不辣、厨具与当前模式。
- `user_inventory`：每份食材的稳定 key、数量（NULL 表示没数过）、单位、存放位置。
- `user_favorites`：用户与菜谱唯一关联。
- `user_shopping_items`：采购食材、已购状态、来源菜名列表。

个人表全部按认证后的 user_id 查询，不接收客户端 user_id。外键约束食材/菜谱，库存不同单位不合并。用户删除时个人表级联清理；本次没有删除用户的 API。

## 本地运行

```sh
docker compose up -d mysql app nginx
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=KitchenCatalogSeeder
```

本地 API：`http://127.0.0.1:8087/api`。前端开发版使用该地址，体验版/正式版仍为 `https://eat.guozeshui.top`。微信开发工具本地调试时关闭 request 合法域名校验；真机调试将 developmentBaseUrl 换为电脑局域网 IP。没有登录后门，微信登录必须使用真实临时 code；接口集成测试使用独立测试账号的临时 Token，结束后清理。

`database/catalog.json` 是菜谱与食材初始化源。Seeder 幂等更新相同 ID，不清空用户表、不删除旧食材；关于我们只在首次初始化写入，保留之后的修改。菜谱内容修改后更新初始化源并重新执行 Seeder。现有 DatabaseSeeder 已改为调用菜谱 Seeder，不再创建示例用户。

## 接口与同步协议

- GET `/api/catalog`：数据库返回完整食材和菜谱；前端有随包与本机缓存，离线可继续推荐。
- GET `/api/about`：品牌与联系方式。
- GET `/api/kitchen`：需要 Bearer Token，返回 `{revision,state}`。
- PUT `/api/kitchen`：`{revision,requestId,state}`，requestId 为 UUID。成功整个厨房状态原子入库，版本加一；相同请求重试幂等，旧 revision 返回 HTTP 409。

完整状态包括 selected/quick/mild/equipment/servings/homeMode/recipeSource/inventory/favorites/shopping。一次“买好后入库”中，库存增加和采购清单清理同一事务提交。

前端本地状态按 API 环境与账号 ID 隔离，游客沿用旧存储 key。登录后直接读取账号数据，不提供游客数据导入或迁移流程。修改先写本机，500ms 合并上传；发送中的快照连同请求 ID 持久保存，网络失败可重试。响应丢失不会重复入库；上传期间的新编辑不会被旧响应清掉。再次打开、网络恢复或手动同步时重试。

冲突不自动覆盖，用户在“我的 → 数据同步”选择使用账号数据或本机数据；替换前保存双方本机备份，最近 5 次位于账号存储 key 的 `:backups`，供恢复排查。退出前尝试完成同步。清除微信缓存或卸载小程序会丢失尚未上传的内容。

## 验证

```sh
php artisan test --filter 'KitchenDatabaseTest|WechatLoginTest'
# 前端目录，Node 18+
node --test tests/*.test.js
```

数据库迁移采用新增表，不更改现有用户数据。生产上线时按 deployment.md 的生产 compose 命令执行 migrate，并额外执行 `php artisan db:seed --class=KitchenCatalogSeeder --force`。
