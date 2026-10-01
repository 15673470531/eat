# 小程序管理菜谱

权限复用已有 `users.is_admin` 字段，数据库值为 1 的用户是管理员。登录和用户信息接口返回 `isAdmin`，小程序“我的”仅对管理员展示“管理菜谱”。后端每次调用管理接口都读取用户权限，不接受客户端传入的管理员标志；本次不自动提升任何现有账号权限。

## 部署

更新后端代码后执行新增迁移：

```sh
php artisan migrate --force
```

如果启用了路由缓存，更新后执行 `php artisan route:cache`。现有数据不需要重新导入。

本地 Docker 对应 `docker exec eatwhat-app php artisan migrate --force`。小程序重新编译，管理员重新进入“我的”会刷新权限；若是在微信后台发体验版，需要同时更新小程序包。

将目标用户设为管理员需确认其“我的”页面用户 ID 后，在数据库将对应行 is_admin 设为 1；没有通过小程序修改权限的接口。

## 功能

- 菜谱：搜索菜名/主料、新增、编辑、复制、预览后保存、下架/上架。
- 食材：名称、分类、别名维护；显示上架的主料菜数量，筛出“暂无菜谱”“只有搭配菜”。调料不当作单主料缺口。
- 选中食材可直接给它新增一道菜；编辑用料时可新增缺失食材，返回保留菜谱草稿。
- 菜谱默认 2 人份，主料/必需调料/可选材料分开；可选材料必须写省略说明。

## 数据与更新

新增 ingredients.revision、recipes.revision、recipes.is_active，以及名称唯一约束。修改带当前 revision，旧版本返回 409，管理员返回列表重新打开编辑；材料变更与菜谱在同一事务提交。

公共 catalog 返回下架标志以覆盖客户端缓存，前端推荐、收藏列表、详情过滤下架菜。数据库保留关联记录，支持再次上架；离线设备下次联网刷新后生效。

Seeder 不再覆盖 revision > 1 的食材或菜谱，保护管理员修改；如需更新已编辑内容，请走管理页面。原始 catalog.json 是初始化数据，不是实时数据库备份。

管理 API 在 /api/admin 下，全部需要 Sanctum Token 和 RequireAdmin：
- GET catalog
- POST ingredients / PUT ingredients/{id}
- POST recipes / PUT recipes/{id}
- PATCH recipes/{id}/status

可运行 `php artisan test --filter AdminCatalogTest` 验证权限、编辑、复制、下架和版本冲突。
