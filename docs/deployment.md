# eatWhat 部署

沿用 clothes 的 PHP-FPM + Nginx + 宿主机 MySQL 架构，独立数据库、账号和容器项目。这里由宿主机 Nginx 负责 HTTPS，反向代理到 127.0.0.1:8087；不复用 clothes 的证书、密钥或数据库。

1. 上传项目到独立目录；不要上传本地 .env、数据库和测试缓存。复制 `.env.production.example` 为 `.env`，填写域名、MySQL 账号密码、该小程序的 WECHAT_APPID / WECHAT_APPSECRET。数据库提前创建为 utf8mb4，账号仅授权 eatwhat 数据库。
2. 所有生产命令都显式指定配置文件，避免加载本地 MySQL override：

```sh
docker compose -p eatwhat -f compose.production.yml build
docker compose -p eatwhat -f compose.production.yml run --rm app composer install --no-dev --prefer-dist --optimize-autoloader
docker compose -p eatwhat -f compose.production.yml run --rm app php artisan key:generate
# 确保 storage 和 bootstrap/cache 可供容器 UID 1000 写入；不要全目录 chmod 777。
docker compose -p eatwhat -f compose.production.yml run --rm app php artisan migrate --force
docker compose -p eatwhat -f compose.production.yml run --rm app php artisan config:cache
docker compose -p eatwhat -f compose.production.yml run --rm app php artisan route:cache
docker compose -p eatwhat -f compose.production.yml up -d
```

`key:generate` 仅首次部署执行，更新时保留原 APP_KEY；更新迁移前备份数据库。已有 eatwhat 容器若占用 8087，先确认并停止旧 eatwhat 服务，勿停止 clothes。

3. 宿主机配置 HTTPS 域名和对应证书，转发到 `http://127.0.0.1:8087`，传递 Host、X-Forwarded-For、X-Forwarded-Proto。开放公网 443，8087 仅本机监听。检查 `https://你的域名/up`。
4. 小程序 `config/api.js` 的 productionBaseUrl 填相同 HTTPS 域名（不含 /api），微信后台添加 request 合法域名。AppID 必须与前端 project.config.json 一致；AppSecret 仅留在服务端。
5. 首次初始化或更新菜谱：`docker compose -p eatwhat -f compose.production.yml run --rm app php artisan db:seed --class=KitchenCatalogSeeder --force`。

6. 真机验证：我的 → 微信登录 → 退出 → 再登录，数据库应为同一用户。失败时只提示失败，绝不降级成开发账号。Token 有效期 30 天，退出撤销当前 token。

7. 「关于我们」内容（含公众号文章链接、入口文案）存在 `app_settings.about`，换链接不用发小程序版本：

```sh
docker compose -p eatwhat -f compose.production.yml run --rm app php artisan about:set              # 查看
docker compose -p eatwhat -f compose.production.yml run --rm app php artisan about:set article https://mp.weixin.qq.com/s/新地址
```

不带参数只打印当前内容，不改库；字段没写过时下发代码默认值（见 `app/Services/AboutSettings.php`）。

## 接口
- POST /api/user/login，JSON `{ "code": "wx.login 返回的临时凭证" }`，每 IP 每分钟最多 10 次。
- GET /api/user/info；POST /api/user/logout：Authorization: Bearer token。
- POST /api/user/update-name：已登录，JSON `{ "name": "昵称" }`。
- 正常响应 `{code:0,msg:"success",data:...}`；鉴权失败 HTTP 401。

当前版本登录后同步冰箱、收藏、采购清单和偏好；游客使用独立本地存储，登录后直接读取账号数据。数据库设计和本地联调见 database.md。登录不自动获取微信头像昵称或手机号。关于我们页不展示开发者邮箱、微信等个人联系方式，联系入口统一走「联系客服」与公众号。

接口自动测试使用 Http::fake 模拟微信服务，不等同于真实微信联调。微信流程参考：https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/user-login/code2Session.html
