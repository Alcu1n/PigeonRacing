# Pigeon Race Registration System / 赛鸽赛事报名系统

An online registration system for pigeon races: Laravel owns trusted business rules and administration, Vue owns fast mobile member registration, MySQL stores final truth, and Redis reduces repeated reads. / 一个赛鸽赛事在线报名系统：Laravel 负责可信业务规则与后台，Vue 负责高效手机端报名，MySQL 保存最终事实，Redis 降低重复读取。

## Structure / 结构

- `backend/`: Laravel 13 API, Filament admin panel, migrations, seeders, imports, exports, and registration services. / Laravel 13 API、Filament 后台、迁移、种子、导入导出与报名服务。
- `frontend/member-h5/`: Vue 3 mobile H5 member app with local matrix state and draft persistence. / Vue 3 手机端会员 H5，包含本地矩阵状态与草稿保存。
- `docker/`: Nginx and PHP runtime configuration for single-server deployment. / 单机部署所需的 Nginx 与 PHP 运行配置。
- `赛鸽赛事报名系统产品说明文档.md`: Original product specification and acceptance source. / 原始产品说明与验收来源。

## Core Protocol / 核心协议

Member pages load registration bootstrap data once, perform all selection and amount calculation locally, then submit one normalized `entries` payload. / 会员页一次性加载报名初始化数据，所有选择与金额计算在本地完成，最后提交统一的 `entries` 数据。

The backend never trusts frontend totals: it validates race state, `config_version`, project `group_size`, pigeon ownership, duplicate limits, then writes snapshots in a database transaction. / 后端绝不信任前端金额：校验赛事状态、`config_version`、项目 `group_size`、足环归属和重复限制，并在数据库事务中写入快照。

When code changes, sync the source header, parent `.folder.md`, and this README if module boundaries change. / 修改代码时，同步源文件头、父级 `.folder.md`；若模块边界变化，同步本 README。

## Member Session and Registration Recovery / 会员会话与报名恢复

Member login is an account-switch operation: the API clears the previous `member` guard session before validating the new credentials, and member API JSON responses use `Cache-Control: no-store` to reduce WeChat WebView session-cache risk. / 会员登录是一次账号切换操作：API 会在校验新账号前清理旧的 `member` guard 会话，并且会员 API JSON 响应使用 `Cache-Control: no-store`，降低微信 WebView 会话缓存风险。

The latest successfully submitted database registration is the cross-browser source of truth. / 最近一次成功提交到数据库的报名记录，是跨浏览器恢复的唯一权威来源。

When a member enters a race, bootstrap restores `existing_registration` into the single-pigeon matrix, multi-pigeon groups, and selected-detail view. / 会员进入赛事时，初始化接口会把 `existing_registration` 恢复到单羽矩阵、多羽组合和已选明细视图。

Local drafts only protect unsent same-browser edits: stale config drafts and drafts older than the database submission are discarded. / 本地草稿只保护同一浏览器未提交的编辑：配置过期的草稿，以及早于数据库提交时间的草稿都会被丢弃。

## 生产环境部署（从零开始）

本项目的生产部署本质上是一个单仓单机部署：`backend/` 是 Laravel API 与 Filament 后台，`frontend/member-h5/` 是构建后的会员 H5 静态资源，`docker-compose.yml` 拉起 Nginx、PHP-FPM、队列、调度器、MySQL 和 Redis。正确顺序是先准备配置与依赖，再启动容器，最后做 Laravel 初始化和域名 HTTPS 反向代理。

推荐拓扑：Docker Compose 内部 Nginx 监听 `127.0.0.1:8080`，宿主机 Nginx 或宝塔负责绑定域名、申请 HTTPS，并反向代理到 `127.0.0.1:8080`。不要把 PHP、MySQL、Redis 分散到多套环境里，否则排错成本会无意义地增加。

以下命令假设项目部署在 `/opt/pigeon-racing`，域名示例使用 `feilesg.com`，请替换为你的真实域名。

### 1. 服务器与域名准备

服务器建议使用 Ubuntu 22.04/24.04，至少 2GB 内存。云服务器安全组至少放行：

```text
22    SSH 登录
80    HTTP 域名验证和跳转
443   HTTPS 正式访问
```

`8080` 只建议临时测试使用。正式域名反向代理完成后，不要把 `8080` 暴露到公网。

在域名服务商处添加解析：

```text
A 记录：@    -> 服务器公网 IP
A 记录：www  -> 服务器公网 IP
```

在本机或服务器上验证解析：

```bash
nslookup feilesg.com
ping feilesg.com
```

创建固定部署目录：

```bash
sudo mkdir -p /opt/pigeon-racing
sudo chown -R "$USER":"$USER" /opt/pigeon-racing
cd /opt/pigeon-racing
```

### 2. 安装正确的 Docker

生产服务器必须使用官方 Docker Engine 和 Compose 插件。不要使用 Snap 版 Docker；Snap 版经常把 Compose 文件解析到 `/var/lib/snapd/void`，导致明明有 `docker-compose.yml` 却报找不到配置文件。

如果机器装过 Snap Docker，先清掉：

```bash
snap list | grep docker || true
sudo snap remove docker || true
hash -r
```

安装官方 Docker：

```bash
curl -fsSL https://get.docker.com | bash
sudo systemctl enable docker
sudo systemctl start docker
hash -r
```

确认结果：

```bash
which docker
docker --version
docker compose version
```

正确路径通常是：

```text
/usr/bin/docker
```

如果仍然出现 `-bash: /snap/bin/docker: No such file or directory`，说明当前 shell 缓存了旧路径，执行：

```bash
hash -r
```

不行就退出 SSH 后重新登录。

### 3. 拉取项目代码

```bash
cd /opt/pigeon-racing
git clone https://github.com/Alcu1n/PigeonRacing.git .
```

确认仓库结构：

```bash
ls
```

应该看到：

```text
backend  docker  docker-compose.yml  frontend  README.md
```

用绝对路径验证 Compose 文件，避免 shell 当前目录和面板终端造成误判：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml config --services
```

应该包含：

```text
nginx
app
queue
scheduler
mysql
redis
```

如果这里失败，先解决 Docker 或路径问题，不要继续执行后面的 Laravel 命令。

### 4. 首次启动前修改 docker-compose.yml

生产环境请在第一次启动 MySQL 前修改数据库密码。MySQL 官方镜像只会在 volume 首次初始化时读取 `MYSQL_PASSWORD` 和 `MYSQL_ROOT_PASSWORD`；如果已经启动过，再改 Compose 文件不会自动改库内密码。

编辑 `/opt/pigeon-racing/docker-compose.yml`：

```yaml
services:
  nginx:
    ports:
      - "127.0.0.1:8080:80"

  mysql:
    environment:
      MYSQL_DATABASE: pigeon_registration
      MYSQL_USER: pigeon
      MYSQL_PASSWORD: 替换为强密码
      MYSQL_ROOT_PASSWORD: 替换为root强密码
```

说明：

- `127.0.0.1:8080:80` 表示应用只给服务器本机访问，后面由宿主机 Nginx 或宝塔反向代理。
- `DB_PASSWORD` 必须和这里的 `MYSQL_PASSWORD` 完全一致。
- 密码不要带容易被 shell 或 YAML 误解的字符。必须使用特殊字符时，请用英文引号包住。

如果你确实要临时用公网 IP 加端口测试，可以短时间改成：

```yaml
ports:
  - "8080:80"
```

测试结束后改回 `127.0.0.1:8080:80`。

### 5. 创建后端生产 .env

```bash
cd /opt/pigeon-racing
cp backend/.env.example backend/.env
nano backend/.env
```

推荐生产配置如下，请按你的域名和密码替换：

```env
APP_NAME="赛鸽赛事报名系统"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://feilesg.com
FRONTEND_URL=https://feilesg.com
PUBLIC_STORAGE_URL=/storage
VITE_ASSET_BASE_URL=https://cdn.feilesg.com/

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pigeon_registration
DB_USERNAME=pigeon
DB_PASSWORD=替换为docker-compose里的MYSQL_PASSWORD

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=feilesg.com,www.feilesg.com

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

`APP_KEY` 是 Laravel 应用加密密钥，用于加密 Cookie、Session 和应用内部加密数据。生产环境绝不能留空，但现在先留空，等 Composer 依赖安装完成、容器启动后执行 `php artisan key:generate --force` 自动写入。

`VITE_ASSET_BASE_URL` 只在构建会员端 H5 静态资源时使用。设置为 `https://cdn.feilesg.com/` 后，`dist/index.html` 会从 CDN 加载 JS/CSS/图片，但页面入口、API、Sanctum Cookie 和后台仍然使用 `https://feilesg.com`。

同域部署时 `SESSION_DOMAIN` 保持为空即可。如果你明确要让 `feilesg.com` 和 `www.feilesg.com` 共享登录态，可以设置为：

```env
SESSION_DOMAIN=.feilesg.com
```

### 6. 准备 Laravel 运行目录

Composer 安装依赖时会触发 Laravel 脚本，`bootstrap/cache` 和 `storage` 必须提前存在且可写。

```bash
cd /opt/pigeon-racing
mkdir -p backend/bootstrap/cache \
  backend/storage/framework/cache \
  backend/storage/framework/sessions \
  backend/storage/framework/views \
  backend/storage/logs
chmod -R 775 backend/bootstrap/cache backend/storage
```

如果后续仍遇到权限问题，再执行：

```bash
sudo chown -R "$USER":"$USER" backend/bootstrap/cache backend/storage
chmod -R 775 backend/bootstrap/cache backend/storage
```

### 7. 构建 PHP 镜像并安装后端依赖

本项目 PHP 镜像在 `docker/php/Dockerfile`，已包含 Laravel 和 Excel 导入导出需要的 `gd`、`zip`、`intl`、`pdo_mysql` 等扩展。

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml build app queue scheduler
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
```

如果出现：

```text
vendor/autoload.php: Failed to open stream
```

说明 Composer 没有成功完成，不能执行任何 Artisan 初始化命令。先修复 Composer 安装。

如果出现：

```text
The /var/www/backend/bootstrap/cache directory must be present and writable.
```

重新创建目录并重试：

```bash
cd /opt/pigeon-racing
mkdir -p backend/bootstrap/cache \
  backend/storage/framework/cache \
  backend/storage/framework/sessions \
  backend/storage/framework/views \
  backend/storage/logs
chmod -R 775 backend/bootstrap/cache backend/storage
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
```

### 8. 构建会员端前端资源

Docker Nginx 会读取 `frontend/member-h5/dist`，所以必须先构建前端。

安装 Node.js 20+ 或 22+ 后执行：

```bash
cd /opt/pigeon-racing/frontend/member-h5
npm ci
npm run build
cd /opt/pigeon-racing
```

如果已经把 `dist/assets/` 上传到阿里云 OSS 并通过 `cdn.feilesg.com` 开启 CDN，生产构建可改为：

```bash
cd /opt/pigeon-racing/frontend/member-h5
npm ci
VITE_ASSET_BASE_URL=https://cdn.feilesg.com/ npm run build
cd /opt/pigeon-racing
```

确认构建结果：

```bash
ls frontend/member-h5/dist
```

应该至少看到：

```text
index.html  assets
```

如果没有 `dist`，访问 `/login` 时 Nginx 只能返回错误或空内容。

### 9. 启动容器

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d
docker compose -f /opt/pigeon-racing/docker-compose.yml ps
```

正常应看到 `nginx`、`app`、`queue`、`scheduler`、`mysql`、`redis` 都在运行。

注意：必须先 `up -d`，再使用 `exec`。如果容器未运行就执行：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan key:generate
```

会出现：

```text
service "app" is not running
```

这不是 Laravel 问题，是容器还没启动。

### 10. 初始化 Laravel

生成 `APP_KEY`：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan key:generate --force
```

创建上传文件公开访问链接：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan storage:link
```

发布 Filament 后台资源：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan filament:assets
```

清理并缓存生产配置：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

### 11. 初始化数据库

先测试数据库连接：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate:status
```

如果返回：

```text
Migration table not found
```

这通常不是错误，反而说明 Laravel 已经连上数据库，只是还没有执行迁移。继续执行：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
```

迁移完成后再缓存路由和视图：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan route:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan view:cache
```

如果出现数据库密码错误，例如：

```text
SQLSTATE[HY000] [1045] Access denied for user 'pigeon'
```

根因只有一个：`backend/.env` 的 `DB_PASSWORD` 和 MySQL volume 中真实的 `pigeon` 用户密码不一致。

如果数据库还没有正式数据，可以直接重建 MySQL volume：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml down -v
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d
```

注意：`down -v` 会删除数据库数据，只能在空库首次部署时使用。

如果数据库已经有数据，不要删 volume，用 root 登录 MySQL 修改用户密码：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec mysql mysql -uroot -p
```

输入 `MYSQL_ROOT_PASSWORD` 后执行：

```sql
ALTER USER 'pigeon'@'%' IDENTIFIED BY '替换为backend/.env里的DB_PASSWORD';
FLUSH PRIVILEGES;
EXIT;
```

然后刷新 Laravel 配置缓存：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate:status
```

### 12. 创建第一个后台管理员

不要在生产环境随意运行演示 seed。用下面命令创建管理员，把邮箱和密码替换掉：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Models\User::query()->updateOrCreate(["email"=>"admin@example.com"], ["name"=>"系统管理员", "password"=>Illuminate\Support\Facades\Hash::make("替换为后台管理员强密码")]); echo "admin ready\n";'
```

后台地址：

```text
https://feilesg.com/admin
```

会员端地址：

```text
https://feilesg.com/login
```

### 13. 宿主机 Nginx 绑定域名和 HTTPS

如果不用宝塔，推荐在宿主机安装 Nginx 和 Certbot：

```bash
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
```

创建站点配置：

```bash
sudo nano /etc/nginx/sites-available/pigeon-racing.conf
```

写入：

```nginx
server {
    listen 80;
    server_name feilesg.com www.feilesg.com;

    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

启用站点：

```bash
sudo ln -s /etc/nginx/sites-available/pigeon-racing.conf /etc/nginx/sites-enabled/pigeon-racing.conf
sudo nginx -t
sudo systemctl reload nginx
```

先测试 HTTP：

```text
http://feilesg.com/login
http://feilesg.com/admin
```

确认 HTTP 可访问后申请 HTTPS：

```bash
sudo certbot --nginx -d feilesg.com -d www.feilesg.com
sudo certbot renew --dry-run
```

HTTPS 生效后确认 `backend/.env`：

```env
APP_URL=https://feilesg.com
FRONTEND_URL=https://feilesg.com
SANCTUM_STATEFUL_DOMAINS=feilesg.com,www.feilesg.com
```

后台样式依赖 Laravel 正确认出原始请求协议。宿主机 Nginx 必须保留 `X-Forwarded-Proto $scheme`，项目启动层会信任该代理头，避免 Filament CSS/JS 在 HTTPS 页面里被生成为 `http://...`。

刷新 Laravel 缓存：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

### 14. 宝塔面板部署方式

宝塔推荐只做域名、SSL 和反向代理，应用仍然用 Docker Compose 运行。这是最稳的方式。

流程：

1. 在服务器 SSH 中按第 1 到第 12 步完成 Docker Compose 部署。
2. `docker-compose.yml` 中保持 `127.0.0.1:8080:80`。
3. 宝塔面板添加站点，域名填 `feilesg.com` 和 `www.feilesg.com`。
4. PHP 版本可选“纯静态”，因为真实应用由 Docker 承载。
5. 在站点设置里打开“反向代理”，目标 URL 填：

```text
http://127.0.0.1:8080
```

如果宝塔允许填写自定义代理配置，使用：

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

6. 在宝塔站点 SSL 中申请 Let's Encrypt 证书，并开启强制 HTTPS。
7. 回到服务器刷新 Laravel 缓存：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

不推荐用宝塔传统 PHP 站点直接跑本项目，除非你明确知道如何维护 PHP 扩展、Composer、Node、队列、调度器、Redis 和 Nginx 路由。这个项目的 Docker Compose 已经把这些边界固定好了，绕开它只会制造更多不确定性。

### 15. 日常更新流程

生产环境已经部署完成后，后续从 GitHub 更新代码不要重新初始化服务器、不要删除 MySQL volume、不要重新生成 `APP_KEY`、不要重新申请 SSL。只需要在服务器上进入 `/opt/pigeon-racing`，按下面顺序发布即可。

先确认当前在正确目录：

```bash
cd /opt/pigeon-racing
pwd
docker compose -f /opt/pigeon-racing/docker-compose.yml ps
```

确认输出路径是 `/opt/pigeon-racing`，并且容器服务名包含 `app`、`nginx`、`mysql`、`redis`、`queue`、`scheduler`。

正式更新：

```bash
cd /opt/pigeon-racing

git fetch origin
git status --short
git pull --ff-only

docker compose -f /opt/pigeon-racing/docker-compose.yml build app queue scheduler
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader

cd frontend/member-h5
npm ci
VITE_ASSET_BASE_URL=https://cdn.feilesg.com/ npx vite build
cd /opt/pigeon-racing

docker compose -f /opt/pigeon-racing/docker-compose.yml up -d --remove-orphans
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan filament:assets
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan route:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan view:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app queue scheduler nginx
```

如果使用 CDN 缓存静态文件，请在终端执行以下命令来打包 dist 静态文件，解压后将 assets 文件夹上传到 OSS 存储中：

```bash
cd /opt/pigeon-racing/frontend/member-h5
tar -czvf dist.tar.gz dist/
```

如果服务器上 `npm run build` 长时间卡住，通常卡在 `vue-tsc --noEmit` 类型检查阶段。生产发布急需更新前端静态资源时，可以临时只执行 Vite 构建，跳过类型检查：

If `npm run build` hangs on the server, it is usually stuck at the `vue-tsc --noEmit` type-check step. For an urgent production asset release, run the Vite build only and skip type checking temporarily:

```bash
cd /opt/pigeon-racing/frontend/member-h5
npx vite build
cd /opt/pigeon-racing
```

注意：`npx vite build` 只是应急构建命令。本地开发或正式发版前仍应优先跑 `npm run build`，因为它会先做 TypeScript 类型检查。
Note: `npx vite build` is an emergency build command only. Prefer `npm run build` for local development and normal releases because it runs TypeScript type checks first.

如果没有使用 OSS/CDN，前端构建命令保持 `npm run build` 即可。如果使用 OSS/CDN，只上传 `frontend/member-h5/dist/assets/` 到 OSS 根目录的 `assets/` 下；`frontend/member-h5/dist/index.html` 仍由服务器 Nginx 提供，用户入口仍是 `https://feilesg.com/login`，不要把用户入口改成 `https://cdn.feilesg.com/login`。

If OSS/CDN is not enabled, keep using `npm run build`. If OSS/CDN is enabled, upload only `frontend/member-h5/dist/assets/` to the OSS `assets/` directory. Keep serving `frontend/member-h5/dist/index.html` from the server Nginx; the public entry remains `https://feilesg.com/login`, not `https://cdn.feilesg.com/login`.

阿里云 OSS/CDN 侧建议配置：

```text
1. OSS 目录：将 dist/assets/ 内文件上传到 OSS 的 assets/ 目录。
2. CDN 域名：cdn.feilesg.com 指向 OSS bucket。
3. 缓存：assets/* 可长缓存，因为 Vite 文件名带 hash；index.html 不建议放 CDN 作为入口。
4. Content-Type：确保 .js 为 application/javascript，.css 为 text/css，.svg 为 image/svg+xml。
5. 跨域：允许 https://feilesg.com 或 * 加载静态资源，避免 module script 被浏览器拦截。
```

说明：

- `git pull --ff-only` 可以避免服务器上出现意外合并提交。如果提示本地有修改，先不要强行覆盖，检查 `git status --short` 输出。
- 只要 GitHub 更新涉及 PHP 代码、Composer 依赖、前端资源或 Dockerfile，都可以直接执行完整流程。
- 如果这次只是修复 HTTPS 后台样式问题，也按完整流程执行；关键步骤是拉取最新 `backend/bootstrap/app.php`，然后清理配置缓存并重启 `app` 与 `nginx`。
- 如果这次涉及 Logo、Excel 报告等公开上传文件访问问题，也按完整流程执行；关键步骤是拉取最新 `backend/routes/web.php`、公开存储控制器与 `docker/nginx/default.conf`，然后清理路由缓存并重启 `app` 与 `nginx`。
- 如果这次只是界面文案或状态显示更新，例如把报名状态从 `pending_confirmation` 改为“未确认/已确认”，仍然要同时执行后端构建、前端 `npm run build`、`filament:assets`、缓存清理和容器重启。后台 Filament 页面来自 Laravel，会员端页面来自 Vite 构建产物，漏掉任一边都会出现一边已更新、一边仍显示旧文案。
- For UI/status-label-only releases, still run the full backend build, frontend build, Filament asset publish, cache clear, and container restart. The admin UI is served by Laravel while the member H5 is a built Vite app; skipping one side leaves stale labels in production.
- 如果这次涉及足环 Excel 大批量导入、上传大小、413 错误或 PHP 运行限制，必须执行完整流程里的 `docker compose ... build app queue scheduler` 和 `restart app queue scheduler nginx`。Nginx 的 `client_max_body_size` 与 PHP 的 `upload_max_filesize/post_max_size/memory_limit` 都是容器运行配置，单纯 `git pull` 不会生效。
- For large Excel import, upload-size, 413, or PHP runtime-limit releases, always rebuild `app queue scheduler` and restart `app queue scheduler nginx`. Nginx body limits and PHP upload/memory limits are runtime config, so `git pull` alone is not enough.
- `migrate --force` 是安全的增量迁移命令，不会清空已有数据。
- 不要执行 `docker compose down -v`，它会删除数据库 volume。

状态显示类更新发布后，按下面顺序做一次快速验收。/ After a status-display release, verify in this order:

```text
1. 打开 https://feilesg.com/admin，进入“报名记录”，确认状态列只显示“已确认”或“未确认”，不再显示 pending_confirmation。
2. 在后台点击某条报名记录进入详情，确认“确认状态”使用同样中文文案和颜色。
3. 打开 https://feilesg.com/login，以会员账号登录，进入个人信息里的报名记录，确认状态标签为“已确认/未确认”。
4. 打开会员端报名提交成功页或历史报名明细页，确认状态不再显示英文枚举。
5. 若仍看到旧文案，优先强制刷新浏览器；仍未更新时重新执行 optimize:clear、config:cache、route:cache、view:cache，并确认 frontend/member-h5 已重新 npm run build。
```

检查服务：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml ps
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 app
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 nginx
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 queue
```

更新后验证：

```text
https://feilesg.com/login
https://feilesg.com/admin
```

如果启用了 OSS/CDN，再额外验证：

```text
1. 打开 https://feilesg.com/login，而不是 https://cdn.feilesg.com/login。
2. 浏览器 Network 中 JS/CSS/图片请求来自 https://cdn.feilesg.com/assets/。
3. /sanctum/csrf-cookie、/api/member/branding、/api/member/login 仍然请求 https://feilesg.com。
4. 会员登录、赛事列表、报名提交、后台 /admin 都正常。
```

如果后台能打开但没有样式，先强制刷新浏览器缓存；仍然无样式时，在服务器执行：

```bash
cd /opt/pigeon-racing
grep -E 'APP_URL|FRONTEND_URL|SANCTUM_STATEFUL_DOMAINS' backend/.env
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan filament:assets
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app nginx
```

如果品牌 Logo 上传后后台显示很小的文件大小，或者会员端登录页看不到 Logo，按下面命令定位：

```bash
cd /opt/pigeon-racing

docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan tinker --execute='
$path = App\Models\AppSetting::getValue(App\Models\AppSetting::BRAND_LOGO_PATH);
dump($path);
dump(Illuminate\Support\Facades\Storage::disk("public")->exists($path));
dump(Illuminate\Support\Facades\Storage::disk("public")->size($path));
dump(Illuminate\Support\Facades\Storage::disk("public")->mimeType($path));
'

curl -I https://feilesg.com/storage/这里替换为上面输出的路径
```

正常结果应该是：`exists=true`，文件大小接近你上传的原始图片大小，`mimeType` 是 `image/png` 或 `image/jpeg`，`curl -I` 返回 `Content-Type: image/png` 或 `image/jpeg`。如果容器内文件大小正常但 `curl -I` 返回 HTML、403、404 或很小的 `Content-Length`，就是 Nginx 没有把 `/storage` 交给 Laravel，或 Laravel 路由缓存还没有刷新。

如果 `http://127.0.0.1:8080/storage/真实路径` 也返回 `403`，先确认 Docker Nginx 已加载新的 `/storage` 专用 PHP-FPM 配置，并刷新 Laravel 路由缓存。这个专用配置必须是 `location ^~ /storage/`，不能让 `/storage` 继续走 `try_files $uri /index.php`，否则存在 `public/storage` 软链接时 Nginx 会优先自己读文件并再次 403。

```bash
cd /opt/pigeon-racing

docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T nginx sh -lc 'nginx -T | grep -A10 "location \\^~ /storage"'

docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan tinker --execute='
$path = App\Models\AppSetting::getValue(App\Models\AppSetting::BRAND_LOGO_PATH);
if ($path) {
    Illuminate\Support\Facades\Storage::disk("public")->setVisibility($path, "public");
}
'

docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan route:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app nginx
```

然后再次验证：

```bash
curl -I http://127.0.0.1:8080/storage/真实路径
curl -I https://feilesg.com/storage/真实路径
```

两个地址都应该返回 `200`，并且 `Content-Type` 是图片类型。

### 16. 备份与恢复

备份数据库和上传文件：

```bash
cd /opt/pigeon-racing
mkdir -p /opt/backups/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T mysql mysqldump -uroot -p你的MYSQL_ROOT_PASSWORD pigeon_registration > /opt/backups/pigeon-racing/pigeon_registration-$(date +%F).sql
tar -czf /opt/backups/pigeon-racing/backend-storage-$(date +%F).tar.gz backend/storage
```

恢复到空环境时：

```bash
cd /opt/pigeon-racing
cat /opt/backups/pigeon-racing/pigeon_registration-YYYY-MM-DD.sql | docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T mysql mysql -uroot -p你的MYSQL_ROOT_PASSWORD pigeon_registration
tar -xzf /opt/backups/pigeon-racing/backend-storage-YYYY-MM-DD.tar.gz -C /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
```

### 17. 常见错误判断

`no configuration file provided: not found`：当前目录不对，或 Docker 不是官方版本。使用绝对路径：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml config --services
```

`open /var/lib/snapd/void/docker-compose.yml: no such file or directory`：Snap Docker 问题。卸载 Snap Docker，安装官方 Docker，执行 `hash -r`。

`service "app" is not running`：容器未启动。先执行：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d
```

`vendor/autoload.php: Failed to open stream`：后端依赖不存在。先执行 Composer：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
```

`bootstrap/cache directory must be present and writable`：Laravel 运行目录缺失或不可写。重新创建目录并授权。

`Migration table not found`：数据库连接正常，只是还没有迁移。执行：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
```

`SQLSTATE[HY000] [1045] Access denied`：数据库密码不一致。检查 `backend/.env` 的 `DB_PASSWORD`、`docker-compose.yml` 的 `MYSQL_PASSWORD`，以及 MySQL volume 中真实用户密码。

`500 Internal Server Error`：先看日志，不要猜：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 app
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 nginx
```

如果 `queue` 日志报 `vendor/autoload.php`，说明 Composer 依赖未装完整；如果 `app` 只显示 `fpm is running`，还要看 Laravel 日志：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app tail -n 100 storage/logs/laravel.log
```

Nginx 日志中的：

```text
can not modify /etc/nginx/conf.d/default.conf (read-only file system?)
```

通常不是致命错误。本项目把 Nginx 配置以只读 volume 挂载进去，官方入口脚本尝试修改默认配置失败，但后面显示 `Configuration complete; ready for start up` 就表示 Nginx 已经启动。

登录循环、419、手机端提交失败：重点检查 `APP_URL`、`FRONTEND_URL`、`SANCTUM_STATEFUL_DOMAINS`、HTTPS 是否一致，然后执行：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

HTTPS 后后台能打开但没有样式：在浏览器开发者工具 Network 里查看 `/css/filament`、`/js/filament` 请求。如果样式链接是 `http://`，说明反向代理协议头或 Laravel 配置缓存不正确；确认宿主机 Nginx 传递 `X-Forwarded-Proto $scheme`，并重新执行 `optimize:clear` 与 `config:cache`。如果请求是 404，重新执行 `php artisan filament:assets`。

## Local Full-Stack Test / 本地完整联调测试

Use Docker for full-stack testing because it matches the intended single-server topology: Nginx serves the built H5 app, Laravel handles `/api`, `/admin`, and `/sanctum`, MySQL stores business data, and Redis handles cache/session/queue. / 完整联调建议使用 Docker，因为它贴近目标单机部署拓扑：Nginx 提供构建后的 H5，Laravel 处理 `/api`、`/admin`、`/sanctum`，MySQL 保存业务数据，Redis 处理缓存、会话与队列。

The Compose project name is fixed as `pigeon-racing` in `docker-compose.yml`, so Docker will not derive an invalid empty project name from the Chinese repository folder. / `docker-compose.yml` 已固定 Compose 项目名为 `pigeon-racing`，因此 Docker 不会再从中文仓库目录推导出非法空项目名。

### 1. Prepare Environment / 准备环境

Run from the repository root. / 在仓库根目录执行。

```bash
cd /Users/alcuin/Coding/在线赛事报名系统
cp backend/.env.example backend/.env
```

Install backend dependencies in the PHP container. / 在 PHP 容器内安装后端依赖。

```bash
docker compose build app
docker compose run --rm app composer install
```

If Composer reports a missing PHP extension after Dockerfile changes, rebuild the app image before running Composer again. / 如果修改 Dockerfile 后 Composer 仍提示缺少 PHP 扩展，请先重建 app 镜像再运行 Composer。

```bash
docker compose build --no-cache app
docker compose run --rm app composer install
```

Generate the Laravel application key. / 生成 Laravel 应用密钥。

```bash
docker compose run --rm app php artisan key:generate
```

Create the public storage link for uploaded logos and reports. / 创建公开存储链接，用于访问上传的 Logo 和报告文件。
Keep `PUBLIC_STORAGE_URL=/storage` for same-origin access through localhost, LAN IP, Cloudflare Tunnel, or the production domain. / 同源访问请保持 `PUBLIC_STORAGE_URL=/storage`，这样 localhost、局域网 IP、Cloudflare Tunnel 或正式域名都会使用当前访问域名。

```bash
docker compose run --rm app php artisan storage:link
```

Publish Filament admin assets. / 发布 Filament 后台资源。

```bash
docker compose run --rm app php artisan filament:assets
```

Build the member H5 app. / 构建会员端 H5。

```bash
cd frontend/member-h5
npm install
npm run build
cd ../..
```

### 2. Create Database and Demo Data / 创建数据库与演示数据

Start MySQL and Redis first if they are not already running. / 如果 MySQL 和 Redis 尚未运行，先启动它们。

```bash
docker compose up -d mysql redis
```

Run migrations and seed demo records. / 执行迁移并写入演示数据。

```bash
docker compose run --rm app php artisan migrate --seed
```

The seeder creates one admin, one member, one open race, several single/multi projects, and 20 pigeon rings. / 种子会创建一个管理员、一个会员、一场开放赛事、多个单羽/多羽项目和 20 个足环。

### 3. Start Full Stack / 启动完整服务

Build images first, then start containers. This avoids Docker Compose's `up --build` Bake path, which may fail on some Docker Desktop versions with a `x-docker-expose-session-sharedkey` gRPC header error. / 先构建镜像，再启动容器。这样可以避开部分 Docker Desktop 版本中 `docker compose up --build` 触发的 Bake 路径错误，例如 `x-docker-expose-session-sharedkey` gRPC header 报错。

```bash
docker compose build app queue scheduler
docker compose up -d
```

If Docker still enters the Bake build path, disable Compose Bake for this command. / 如果 Docker 仍进入 Bake 构建路径，请对此命令禁用 Compose Bake。

```bash
COMPOSE_BAKE=false docker compose build app queue scheduler
docker compose up -d
```

Open these URLs. / 打开以下地址。

```text
Member H5 / 会员端:
http://localhost:8080/login

Admin panel / 后台:
http://localhost:8080/admin
```

For phone testing on the same Wi-Fi, use the computer's LAN IP with port `8080`; do not omit the port. The backend accepts the current request host for cookie auth, protected member APIs authenticate with the `member` guard, and `SESSION_DOMAIN` should stay empty so cookies match both `localhost` and LAN IP hosts. / 手机同 Wi-Fi 测试时，使用电脑局域网 IP 并带上 `8080` 端口；不要省略端口。后端会接受当前请求 Host 的 Cookie 鉴权，受保护会员 API 明确使用 `member` guard，`SESSION_DOMAIN` 应保持为空，这样 Cookie 才能同时匹配 `localhost` 与局域网 IP Host。

```text
Phone member H5 / 手机会员端:
http://192.168.1.82:8080/login
```

If login works on the phone but submit shows `提交失败`, clear Laravel's cached route/config after pulling code changes, then log in again on the phone. / 如果手机端能登录但提交显示 `提交失败`，拉取代码变更后先清理 Laravel 路由/配置缓存，再在手机端重新登录。

```bash
docker compose exec app php artisan optimize:clear
docker compose restart app nginx
```

Demo accounts. / 演示账号。

```text
Member / 会员:
Phone / 手机号: 13800000000
Password / 密码: password

Admin / 后台:
Email / 邮箱: admin@example.com
Password / 密码: password
```

### 4. What to Test / 建议测试流程

Member side. / 会员端。

1. Log in with the demo member account. / 使用演示会员账号登录。
2. Enter `2026 春季大奖赛`. / 进入 `2026 春季大奖赛`。
3. In `单羽组`, click matrix cells and confirm the amount bar changes locally. / 在 `单羽组` 点击矩阵单元格，确认底部金额栏本地变化。
4. Search by full ring number or tail digits, then clear search and confirm selected cells remain selected. / 用完整足环号或尾号搜索，再清空搜索，确认已选状态不丢失。
5. In `多羽组`, select a project, choose the required number of rings, and confirm a group. / 在 `多羽组` 选择项目、按要求选择足环并确认成组。
6. Open `已选明细`, verify grouped details and total amount. / 打开 `已选明细`，核对分组明细和总金额。
7. Submit and verify the success result page. / 提交并确认成功结果页。

Admin side. / 后台。

1. Log in with the demo admin account. / 使用演示管理员账号登录。
2. Check member, pigeon, race, project, and registration resources. / 查看会员、足环、赛事、项目和报名资源。
3. Open `会员管理 -> 导入 Excel` to preview and import member files. / 打开 `会员管理 -> 导入 Excel`，预览并导入会员档案。
4. Open `系统设置 -> 品牌设置`, upload a PNG/JPG logo, verify the file row finishes loading, and verify it appears at the top of the member login page. / 打开 `系统设置 -> 品牌设置`，上传 PNG/JPG Logo，确认文件行加载完成，并确认它显示在会员登录页顶部。
5. Confirm a pending registration. / 确认一条待确认报名。
6. Edit a race project and verify `config_version` policy before member submission in later tests. / 后续测试可修改赛事项目并验证会员提交前的 `config_version` 策略。

### 5. Admin Excel Import and Export / 后台 Excel 导入与导出

Member import. / 会员导入。

1. Open `后台 -> 会员管理 -> 导入 Excel`. / 打开 `后台 -> 会员管理 -> 导入 Excel`。
2. Use the fixed header `序号，棚号，参赛名，手机号，密码`; `.xlsx` and `.xls` are accepted, max 10MB. / 使用固定表头 `序号，棚号，参赛名，手机号，密码`；支持 `.xlsx` 与 `.xls`，最大 10MB。
3. `棚号` and `参赛名` are required; `手机号` and `密码` may be blank. / `棚号` 与 `参赛名` 必填；`手机号` 与 `密码` 可留空。
4. Existing loft numbers are updated by non-empty Excel values only; blank phone/password cells do not overwrite existing values. / 已存在棚号只用 Excel 中非空字段更新；空手机号或空密码不会覆盖现有值。
5. Any non-empty imported or admin-set password marks the member as requiring a first-login password change. / 任何非空导入密码或后台设置密码都会标记会员首次登录必须修改密码。

Pigeon import. / 足环导入。

1. Open `后台 -> 足环管理 -> 导入 Excel`. / 打开 `后台 -> 足环管理 -> 导入 Excel`。
2. Use the fixed header `序号，会员棚号，会员参赛名，足环号码`; `.xlsx` and `.xls` are accepted, max 50MB. / 使用固定表头 `序号，会员棚号，会员参赛名，足环号码`；支持 `.xlsx` 与 `.xls`，最大 50MB。
3. Click `预览导入` before writing data; the preview shows valid rows, failed rows, duplicate rings, new members, participant-name updates, and only the first 50 sample rows. The full source rows stay in a server-side preview cache so 50,000-row imports do not make Livewire confirmation requests too large. / 写入前点击 `预览导入`；预览会显示可导入行、失败行、重复足环、新建会员、参赛名更新和前 50 行样例。完整源数据保存在服务端预览缓存中，避免 5 万行导入时 Livewire 确认请求过大。
4. Click `确认导入`; missing loft numbers create member files with empty phone/password and first-login password-change policy, so they cannot log in until an admin fills credentials. / 点击 `确认导入`；缺失棚号会创建手机号和密码为空且带首次改密策略的会员档案，管理员补齐凭据前不能登录。
5. If failures exist, download the generated error report from the import result panel. / 如有失败行，在导入结果区域下载错误报告。
6. Use `删除所有足环` only for guarded cleanup before reimport; if any registration detail references a pigeon, deletion is blocked to protect historical records. / 仅在重新导入前使用 `删除所有足环` 做受保护清理；如已有报名明细引用足环，系统会阻止删除以保护历史记录。

Registration export. / 报名导出。

1. Open `后台 -> 报名记录 -> 导出 Excel`. / 打开 `后台 -> 报名记录 -> 导出 Excel`。
2. Select a race and download the matrix workbook. / 选择赛事并下载矩阵表格。
3. The workbook starts with a summary header containing race name, registration deadline, and per-project counts, then the data table. / 表格顶部先显示赛事名称、报名截止时间和各项目数量统计，再显示数据表。
4. Columns are `序号、会员棚号、会员参赛名、足环号码` plus each project; single-pigeon rows use `✓`, multi-pigeon groups export as one unique row with an empty `足环号码` cell and comma-separated ring numbers only in the group project cell. / 列为 `序号、会员棚号、会员参赛名、足环号码` 加各比赛项目；单羽行用 `✓`，多羽组合按唯一组合导出为一行，`足环号码` 单元格留空，只在对应项目单元格内用逗号分隔显示足环号。
5. All used cells have solid borders for printing and manual review. / 所有已使用单元格添加实色边框，方便打印和人工核对。

### 6. Member Profile and First Login Password Change / 会员档案与首次登录改密

1. After login, members can open `个人信息` from the top-right action area. / 登录后，会员可从右上角操作区打开 `个人信息`。
2. The profile page shows loft number, participant name, phone, password update form, and owned pigeon rings. / 个人档案页显示棚号、参赛名、手机号、改密表单与名下足环。
3. Members created by admin password entry, member Excel import with password, or pigeon import auto-creation require a first-login password change. / 通过后台设置密码、会员 Excel 导入密码或足环导入自动创建的会员需要首次登录改密。
4. Members flagged for password change are redirected to `/profile?forcePassword=1` and cannot enter race list or registration pages until the password is updated. / 被标记改密的会员会跳转到 `/profile?forcePassword=1`，改密完成前不能进入赛事列表或报名页。

### 7. Reset Local Data / 重置本地数据

Stop services but keep data. / 停止服务但保留数据。

```bash
docker compose down
```

Delete MySQL volume and start from a clean database. / 删除 MySQL 数据卷并从空数据库重来。

```bash
docker compose down -v
docker compose up -d mysql redis
docker compose run --rm app php artisan migrate --seed
docker compose build app queue scheduler
docker compose up -d
```

### 8. Frontend-Only Development / 仅前端开发

For fast UI testing without backend, run Vite directly. The registration screen has a development-only demo bootstrap fallback when the backend API is unavailable. / 如果只想快速测试 UI，可直接运行 Vite；当后端 API 不可用时，报名页会使用仅开发环境启用的演示初始化数据。

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/frontend/member-h5
npm install
npm run dev -- --port 5173
```

Open the registration page directly. / 直接打开报名页。

```text
http://localhost:5173/races/1/register
```

Open the login page. Login requires the backend API unless you only inspect the screen. / 打开登录页；登录动作需要后端 API，除非只检查页面。

```text
http://localhost:5173/login
```

### 9. Verification Commands / 验证命令

Backend tests. / 后端测试。

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/backend
php artisan test
composer validate --no-check-publish --strict
```

Frontend tests and production build. / 前端测试与生产构建。

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/frontend/member-h5
npm test
npm run build
```

List member API routes. / 查看会员 API 路由。

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/backend
php artisan route:list --path=api/member
```
