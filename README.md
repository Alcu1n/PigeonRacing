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

## Production Deployment / 生产部署

Recommended production topology: keep the application running with Docker Compose on `127.0.0.1:8080`, then put the cloud host Nginx or BaoTa site in front for domain binding and HTTPS. This keeps PHP-FPM, queue, scheduler, MySQL, and Redis consistent with local testing. / 推荐生产拓扑：应用本身用 Docker Compose 运行在 `127.0.0.1:8080`，再用云服务器宿主机 Nginx 或宝塔站点做域名绑定与 HTTPS。这样 PHP-FPM、队列、调度器、MySQL、Redis 与本地测试保持一致。

The examples below use `example.com`; replace it with your real domain. / 以下示例使用 `example.com`，请替换为你的真实域名。

Official references for runtime installation: [Docker Engine on Ubuntu](https://docs.docker.com/engine/install/ubuntu/), [Docker Compose plugin](https://docs.docker.com/compose/install/linux/), [Certbot user guide](https://eff-certbot.readthedocs.io/en/stable/using.html), and [BaoTa Linux panel commands](https://www.bt.cn/new/btcode.html). / 运行环境安装官方参考：[Docker Engine on Ubuntu](https://docs.docker.com/engine/install/ubuntu/)、[Docker Compose plugin](https://docs.docker.com/compose/install/linux/)、[Certbot user guide](https://eff-certbot.readthedocs.io/en/stable/using.html) 与 [宝塔 Linux 面板命令](https://www.bt.cn/new/btcode.html)。

### A. DNS and Server Checklist / 域名与服务器检查

1. Buy or prepare a domain, then add DNS records at the domain provider. / 购买或准备域名，然后在域名服务商处添加解析记录。

```text
Type / 类型: A
Host / 主机记录: @
Value / 记录值: your_server_public_ip

Type / 类型: A
Host / 主机记录: www
Value / 记录值: your_server_public_ip
```

2. Wait for DNS to take effect, then verify from your computer. / 等待 DNS 生效后在本机验证。

```bash
ping example.com
nslookup example.com
```

3. Open the cloud firewall/security group. / 放行云服务器安全组。

```text
Required / 必需: 22, 80, 443
Optional / 可选: 8080 only for temporary direct testing; close it after reverse proxy is ready.
可选: 8080 仅用于临时直连测试；反向代理完成后关闭。
```

4. On the server, use a fixed deployment path. / 在服务器上使用固定部署目录。

```bash
sudo mkdir -p /opt/pigeon-racing
sudo chown -R "$USER":"$USER" /opt/pigeon-racing
```

### B. Docker Compose Deployment / Docker Compose 部署

Install Docker Engine and the Compose plugin first. On Ubuntu, follow Docker's official repository method, then verify `docker compose version`. / 先安装 Docker Engine 和 Compose 插件。Ubuntu 建议使用 Docker 官方仓库安装方式，然后用 `docker compose version` 验证。

```bash
docker --version
docker compose version
```

Clone or upload the project. / 克隆或上传项目。

```bash
cd /opt/pigeon-racing
git clone https://github.com/Alcu1n/PigeonRacing.git .
```

Create production environment file. / 创建生产环境配置。

```bash
cp backend/.env.example backend/.env
```

Edit `backend/.env`. / 编辑 `backend/.env`。

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
FRONTEND_URL=https://example.com
PUBLIC_STORAGE_URL=/storage

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pigeon_registration
DB_USERNAME=pigeon
DB_PASSWORD=replace_with_a_strong_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=example.com,www.example.com

REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

For same-domain deployment, keep `SESSION_DOMAIN` empty. If you must share login between `example.com` and `www.example.com`, set `SESSION_DOMAIN=.example.com` and make sure both domains point to the same site. / 同域部署时 `SESSION_DOMAIN` 保持为空即可。如果必须让 `example.com` 和 `www.example.com` 共用登录态，设置 `SESSION_DOMAIN=.example.com`，并确保两个域名都指向同一站点。

Harden `docker-compose.yml` before production: replace default database passwords and bind app Nginx to localhost only. / 生产前加固 `docker-compose.yml`：替换默认数据库密码，并让应用 Nginx 只监听本机。

```yaml
services:
  nginx:
    ports:
      - "127.0.0.1:8080:80"

  mysql:
    environment:
      MYSQL_PASSWORD: replace_with_a_strong_password
      MYSQL_ROOT_PASSWORD: replace_with_a_strong_root_password
```

Build dependencies and assets. / 构建依赖与前端资源。

```bash
docker compose build app queue scheduler
docker compose run --rm app composer install --no-dev --optimize-autoloader

cd frontend/member-h5
npm ci
npm run build
cd ../..
```

Generate the Laravel key, create the public storage link, publish Filament assets, and start services. / 生成 Laravel 密钥、创建公开存储链接、发布 Filament 资源并启动服务。

```bash
docker compose run --rm app php artisan key:generate --force
docker compose run --rm app php artisan storage:link
docker compose run --rm app php artisan filament:assets
docker compose up -d
```

Run migrations. Do not run `migrate --seed` on production unless you intentionally want demo data. / 执行迁移。生产环境不要运行 `migrate --seed`，除非你明确需要演示数据。

```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
```

Create the first admin account without seeding demo data. / 不写入演示数据，直接创建第一个后台管理员。

```bash
docker compose exec -T app php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Models\User::query()->updateOrCreate(["email"=>"admin@example.com"], ["name"=>"系统管理员", "password"=>Illuminate\Support\Facades\Hash::make("replace_with_a_strong_password")]); echo "admin ready\n";'
```

Check containers and logs. / 检查容器与日志。

```bash
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 nginx
docker compose logs --tail=100 queue
```

Temporary direct test before domain proxy. / 域名反向代理前临时直连测试。

```text
http://your_server_public_ip:8080/login
http://your_server_public_ip:8080/admin
```

### C. Host Nginx Domain Binding and HTTPS / 宿主机 Nginx 绑定域名与 HTTPS

Install Nginx and Certbot on the host, then proxy the domain to the Compose Nginx service at `127.0.0.1:8080`. Certbot's Nginx plugin can issue and renew Let's Encrypt certificates after HTTP access works. / 在宿主机安装 Nginx 与 Certbot，把域名反向代理到 Compose Nginx 的 `127.0.0.1:8080`。HTTP 可访问后，Certbot 的 Nginx 插件可签发并续期 Let's Encrypt 证书。

Create `/etc/nginx/sites-available/pigeon-racing.conf`. / 创建 `/etc/nginx/sites-available/pigeon-racing.conf`。

```nginx
server {
    listen 80;
    server_name example.com www.example.com;

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

Enable and test Nginx. / 启用并测试 Nginx。

```bash
sudo ln -s /etc/nginx/sites-available/pigeon-racing.conf /etc/nginx/sites-enabled/pigeon-racing.conf
sudo nginx -t
sudo systemctl reload nginx
```

Open HTTP first. / 先打开 HTTP。

```text
http://example.com/login
http://example.com/admin
```

Issue HTTPS certificate. / 签发 HTTPS 证书。

```bash
sudo certbot --nginx -d example.com -d www.example.com
sudo certbot renew --dry-run
```

After HTTPS works, update `backend/.env` to `https://example.com`, then refresh Laravel caches. / HTTPS 正常后，把 `backend/.env` 改为 `https://example.com`，然后刷新 Laravel 缓存。

```bash
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
```

### D. Update Deployment / 后续更新

Use this sequence for normal releases. / 常规更新使用以下顺序。

```bash
cd /opt/pigeon-racing
git pull
docker compose build app queue scheduler
docker compose run --rm app composer install --no-dev --optimize-autoloader

cd frontend/member-h5
npm ci
npm run build
cd ../..

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan filament:assets
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose up -d
```

### E. Backup and Restore / 备份与恢复

Back up MySQL and uploaded import reports. / 备份 MySQL 与导入错误报告等上传文件。

```bash
mkdir -p /opt/backups/pigeon-racing
docker compose exec -T mysql mysqldump -uroot -proot-secret pigeon_registration > /opt/backups/pigeon-racing/pigeon_registration-$(date +%F).sql
tar -czf /opt/backups/pigeon-racing/backend-storage-$(date +%F).tar.gz backend/storage
```

Restore carefully on an empty target. / 在空目标上谨慎恢复。

```bash
cat /opt/backups/pigeon-racing/pigeon_registration-YYYY-MM-DD.sql | docker compose exec -T mysql mysql -uroot -proot-secret pigeon_registration
tar -xzf /opt/backups/pigeon-racing/backend-storage-YYYY-MM-DD.tar.gz -C /opt/pigeon-racing
docker compose exec -T app php artisan optimize:clear
```

### F. BaoTa Panel Deployment / 宝塔面板部署

There are two BaoTa paths. Prefer `F1`: BaoTa handles domain, SSL, and reverse proxy; Docker Compose still runs the app. `F2` is traditional BaoTa PHP deployment and needs more manual maintenance. / 宝塔有两条路径。优先使用 `F1`：宝塔负责域名、SSL、反向代理，Docker Compose 仍运行应用。`F2` 是传统宝塔 PHP 部署，手工维护更多。

#### F1. Recommended: BaoTa Reverse Proxy + Docker Compose / 推荐：宝塔反向代理 + Docker Compose

1. Install BaoTa on a clean Linux server using the official BaoTa install command for your OS, then log in to the panel. / 在干净 Linux 服务器上使用宝塔官方对应系统安装命令安装面板，然后登录面板。
2. In BaoTa security and the cloud security group, open `80` and `443`; keep `8080` closed to the public after proxy is ready. / 在宝塔安全和云安全组放行 `80`、`443`；反向代理完成后不要公网开放 `8080`。
3. In the BaoTa terminal or SSH, finish the Docker Compose deployment in section B and bind Compose Nginx to `127.0.0.1:8080`. / 在宝塔终端或 SSH 中完成本文 B 节 Docker Compose 部署，并把 Compose Nginx 绑定到 `127.0.0.1:8080`。
4. In BaoTa: `Website` -> `Add site`. Domain: `example.com` and `www.example.com`. PHP version can be `Static` or any value because this site only proxies. / 宝塔中进入 `网站` -> `添加站点`。域名填 `example.com` 和 `www.example.com`。PHP 版本可选 `纯静态` 或任意值，因为该站点只做代理。
5. Open the site settings -> `Reverse Proxy` -> `Add reverse proxy`. / 打开站点设置 -> `反向代理` -> `添加反向代理`。

```text
Name / 名称: pigeon-racing
Target URL / 目标 URL: http://127.0.0.1:8080
Send domain / 发送域名: $host
```

If BaoTa shows custom proxy config, use this block. / 如果宝塔显示自定义代理配置，可使用以下内容。

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

6. In BaoTa site settings -> `SSL`, apply for a Let's Encrypt certificate, enable force HTTPS, then test `/login` and `/admin`. / 在宝塔站点设置 -> `SSL` 中申请 Let's Encrypt 证书，开启强制 HTTPS，然后测试 `/login` 与 `/admin`。
7. Update `backend/.env` to HTTPS domain and refresh Laravel caches. / 更新 `backend/.env` 为 HTTPS 域名并刷新 Laravel 缓存。

```bash
cd /opt/pigeon-racing
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
```

#### F2. Traditional BaoTa PHP Deployment / 传统宝塔 PHP 部署

Use this only if you do not want Docker. You must maintain PHP extensions, Composer, Node, MySQL, Redis, queue worker, and scheduler yourself. / 仅在不想使用 Docker 时使用此方式。你必须自行维护 PHP 扩展、Composer、Node、MySQL、Redis、队列 worker 和 scheduler。

Install runtime in BaoTa. / 在宝塔安装运行环境。

```text
Nginx: 1.24+
PHP: 8.3 or 8.4
MySQL: 8.0+ or 8.4
Redis: installed and running
PHP extensions: fileinfo, intl, pdo_mysql, redis or predis support, sodium, zip, gd
Composer: installed
Node.js: 20+ or 22+
```

Upload project to `/www/wwwroot/pigeon-racing`. / 上传项目到 `/www/wwwroot/pigeon-racing`。

```bash
cd /www/wwwroot/pigeon-racing
cp backend/.env.example backend/.env
```

Edit `backend/.env` for BaoTa services. / 按宝塔服务修改 `backend/.env`。

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
FRONTEND_URL=https://example.com
PUBLIC_STORAGE_URL=/storage

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pigeon_registration
DB_USERNAME=pigeon
DB_PASSWORD=replace_with_a_strong_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_DOMAIN=
SANCTUM_STATEFUL_DOMAINS=example.com,www.example.com

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Install dependencies and build. / 安装依赖并构建。

```bash
cd /www/wwwroot/pigeon-racing/backend
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan filament:assets
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd /www/wwwroot/pigeon-racing/frontend/member-h5
npm ci
npm run build
```

Create admin account. / 创建后台管理员。

```bash
cd /www/wwwroot/pigeon-racing/backend
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Models\User::query()->updateOrCreate(["email"=>"admin@example.com"], ["name"=>"系统管理员", "password"=>Illuminate\Support\Facades\Hash::make("replace_with_a_strong_password")]); echo "admin ready\n";'
```

In BaoTa, add a site for `example.com`. Set site root to the frontend build directory. / 在宝塔添加 `example.com` 站点，站点根目录设为前端构建目录。

```text
Site root / 网站目录:
/www/wwwroot/pigeon-racing/frontend/member-h5/dist
```

Add this Nginx rewrite/config in BaoTa site config. Adjust the PHP socket to your BaoTa PHP version, for example `/tmp/php-cgi-84.sock`. / 在宝塔站点配置中加入以下 Nginx 配置。按你的宝塔 PHP 版本调整 PHP socket，例如 `/tmp/php-cgi-84.sock`。

```nginx
client_max_body_size 20m;
index index.html;

location /assets/ {
    expires 30d;
    add_header Cache-Control "public, immutable";
    try_files $uri =404;
}

location ~ ^/(api|admin|sanctum|up|livewire|livewire-[^/]+|css/filament|js/filament|fonts/filament)(/|$) {
    root /www/wwwroot/pigeon-racing/backend/public;
    try_files $uri /index.php?$query_string;
}

location / {
    try_files $uri $uri/ /index.html;
    add_header Cache-Control "no-store";
}

location ~ \.php$ {
    root /www/wwwroot/pigeon-racing/backend/public;
    fastcgi_pass unix:/tmp/php-cgi-84.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
}
```

Set write permissions. / 设置写权限。

```bash
chown -R www:www /www/wwwroot/pigeon-racing/backend/storage /www/wwwroot/pigeon-racing/backend/bootstrap/cache
chmod -R ug+rw /www/wwwroot/pigeon-racing/backend/storage /www/wwwroot/pigeon-racing/backend/bootstrap/cache
```

Configure queue worker in BaoTa Supervisor Manager or system supervisor. / 在宝塔 Supervisor 管理器或系统 supervisor 中配置队列。

```ini
[program:pigeon-racing-queue]
directory=/www/wwwroot/pigeon-racing/backend
command=php artisan queue:work --tries=3 --sleep=1
autostart=true
autorestart=true
user=www
redirect_stderr=true
stdout_logfile=/www/wwwroot/pigeon-racing/backend/storage/logs/queue.log
```

Configure scheduler in BaoTa cron. Run every minute. / 在宝塔计划任务中配置调度器，每分钟执行。

```bash
cd /www/wwwroot/pigeon-racing/backend && php artisan schedule:run --no-interaction
```

Apply SSL in BaoTa site settings and force HTTPS. Then test: / 在宝塔站点设置中申请 SSL 并开启强制 HTTPS，然后测试：

```text
https://example.com/login
https://example.com/admin
```

### G. Production Troubleshooting / 生产排错

- `419` or login loops: check `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, HTTPS status, and browser cookies. / `419` 或登录循环：检查 `APP_URL`、`SANCTUM_STATEFUL_DOMAINS`、`SESSION_DOMAIN`、HTTPS 状态与浏览器 Cookie。
- Member H5 loads but API fails: check Nginx routes for `/api`, `/sanctum`, and `/livewire`. / 会员端能打开但 API 失败：检查 Nginx 中 `/api`、`/sanctum`、`/livewire` 路由。
- Admin CSS/JS missing: check routes for `/css/filament`, `/js/filament`, and run `php artisan filament:assets`. / 后台 CSS/JS 缺失：检查 `/css/filament`、`/js/filament` 路由，并执行 `php artisan filament:assets`。
- Excel import fails on GD/ZIP: ensure PHP extensions `gd` and `zip` are installed in the active PHP runtime. / Excel 导入因 GD/ZIP 失败：确认当前 PHP 运行环境安装了 `gd` 与 `zip` 扩展。
- Queue jobs do not run: check `queue` container or BaoTa Supervisor process. / 队列任务不执行：检查 `queue` 容器或宝塔 Supervisor 进程。

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
2. Use the fixed header `序号，会员棚号，会员参赛名，足环号码`; `.xlsx` and `.xls` are accepted, max 10MB. / 使用固定表头 `序号，会员棚号，会员参赛名，足环号码`；支持 `.xlsx` 与 `.xls`，最大 10MB。
3. Click `预览导入` before writing data; the preview shows valid rows, failed rows, duplicate rings, new members, and participant-name updates. / 写入前点击 `预览导入`；预览会显示可导入行、失败行、重复足环、新建会员与参赛名更新数量。
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
