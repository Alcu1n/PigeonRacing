# 赛鸽赛事报名系统

赛鸽赛事报名系统是一个单仓项目：后端使用 Laravel API 与 Filament 后台，会员端使用 Vue 3 H5，数据由 MySQL 保存，缓存、会话和队列由 Redis 承载。生产环境推荐使用 Docker Compose 运行应用服务，再由宿主机 Nginx 或宝塔面板绑定域名与 HTTPS。

## 快速目录

- [1. 项目结构](#1-项目结构)
- [2. 核心业务原则](#2-核心业务原则)
- [3. 本地完整测试](#3-本地完整测试)
- [4. 后台 Excel 导入导出](#4-后台-excel-导入导出)
  - [4.4 递进报名类别](#44-递进报名类别站站赛月月赛)
- [5. 会员档案与报名恢复](#5-会员档案与报名恢复)
- [6. 公开信息发布](#6-公开信息发布)
  - [6.1 赛事报名明细发布](#61-赛事报名明细发布)
- [7. 生产环境从零部署](#7-生产环境从零部署)
- [8. 生产环境代码更新](#8-生产环境代码更新)
- [9. 阿里云 OSS 与 CDN 静态资源发布](#9-阿里云-oss-与-cdn-静态资源发布)
- [10. 宝塔面板部署方式](#10-宝塔面板部署方式)
- [11. 备份与恢复](#11-备份与恢复)
- [12. 常见问题排查](#12-常见问题排查)
- [13. 常用验证命令](#13-常用验证命令)

## 1. 项目结构

- `backend/`：Laravel API、Filament 后台、数据库迁移、导入导出、报名事务服务。
- `frontend/member-h5/`：Vue 3 会员端 H5，包含登录、赛事列表、报名、个人档案、报名历史和公开信息页面。
- `docker/`：Docker 运行环境配置，包含 Nginx 与 PHP-FPM 配置。
- `scripts/`：部署辅助脚本，包含会员端静态资源构建并同步到阿里云 OSS 的脚本。
- `docker-compose.yml`：生产和本地联调用的服务编排。
- `赛鸽赛事报名系统产品说明文档.md`：原始产品说明和验收依据。

## 2. 核心业务原则

会员进入报名页时，前端只请求一次报名初始化数据。单羽矩阵、多羽成组、递进阶段整组勾选、金额汇总和已选明细都在浏览器本地完成；提交时普通项目发送 `entries: [{ project_id, pigeon_ids: [] }]`，递进阶段发送 `progressive_entries: [{ category_id, stage_project_id, groups: [{ pigeon_ids: [] }] }]`。

后端不信任前端金额和状态。报名提交必须在数据库事务中重新校验赛事状态、项目配置版本、项目启用状态、足环归属、羽数规则、重复使用规则和金额，然后保存项目、金额、足环快照。

跨浏览器、跨设备恢复报名时，以数据库中最近一次成功提交的报名记录为准。本地草稿只用于同一浏览器中尚未提交的编辑，不能作为跨设备同步来源。

## 3. 本地完整测试

### 3.1 准备 Docker

本地完整联调推荐使用 Docker，因为它最接近生产环境：Nginx 提供会员端页面，Laravel 处理 `/api`、`/admin` 和 `/sanctum`，MySQL 存储业务数据，Redis 处理缓存、会话和队列。

在仓库根目录执行：

```bash
cd /Users/alcuin/Coding/在线赛事报名系统
cp backend/.env.example backend/.env
```

安装后端依赖：

```bash
docker compose build app
docker compose run --rm app composer install
```

如果 Composer 提示缺少 PHP 扩展，先重建镜像：

```bash
docker compose build --no-cache app
docker compose run --rm app composer install
```

生成 Laravel 应用密钥：

```bash
docker compose run --rm app php artisan key:generate
```

创建公开存储链接：

```bash
docker compose run --rm app php artisan storage:link
```

发布 Filament 后台资源：

```bash
docker compose run --rm app php artisan filament:assets
```

构建会员端：

```bash
cd frontend/member-h5
npm install
npm run build
cd ../..
```

### 3.2 初始化数据库

```bash
docker compose up -d mysql redis
docker compose run --rm app php artisan migrate --seed
```

种子数据会创建一个后台管理员、一个会员、一场开放赛事、多个单羽和多羽项目，以及一批测试足环。

### 3.3 启动本地服务

先构建镜像，再启动服务：

```bash
docker compose build app queue scheduler
docker compose up -d
```

如果 Docker Desktop 出现 `x-docker-expose-session-sharedkey` 相关错误，改用：

```bash
COMPOSE_BAKE=false docker compose build app queue scheduler
docker compose up -d
```

访问地址：

```text
会员端：http://localhost:8080/login
后台：http://localhost:8080/admin
```

演示账号：

```text
会员手机号：13800000000
会员密码：password

后台邮箱：admin@example.com
后台密码：password
```

### 3.4 手机同局域网测试

手机和电脑连接同一个 Wi-Fi 后，使用电脑局域网 IP 加 `8080` 端口访问：

```text
http://192.168.1.82:8080/login
```

不要省略端口。`backend/.env` 中的 `SESSION_DOMAIN` 本地测试建议保持为空，这样 Cookie 可以同时匹配 `localhost` 和局域网 IP。

如果手机能登录但提交报名失败，先清理 Laravel 缓存并重启：

```bash
docker compose exec app php artisan optimize:clear
docker compose restart app nginx
```

### 3.5 仅前端开发

只调会员端 UI 时，可以直接启动 Vite：

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/frontend/member-h5
npm install
npm run dev -- --port 5173
```

访问：

```text
http://localhost:5173/login
http://localhost:5173/races/1/register
```

登录动作仍需要后端 API；如果后端不可用，只适合检查纯界面。

## 4. 后台 Excel 导入导出

### 4.1 会员导入

后台路径：`会员管理 -> 导入 Excel`

固定表头：

```text
序号，棚号，参赛名，手机号，密码
```

规则：

- 支持 `.xlsx` 和 `.xls`。
- `棚号`、`参赛名` 必填。
- `手机号`、`密码` 可留空。
- 已存在棚号时，只用 Excel 中的非空字段覆盖；空手机号和空密码不会覆盖已有值。
- 导入或后台设置了非空密码后，会员首次登录必须修改密码。

### 4.2 足环导入

后台路径：`足环管理 -> 导入 Excel`

固定表头：

```text
序号，会员棚号，会员参赛名，足环号码
```

规则：

- 支持 `.xlsx` 和 `.xls`。
- 单次导入目标支持至少 5 万行。
- 预览页只展示前 50 行样例，完整数据保存在服务端预览缓存中，避免确认导入时请求体过大。
- 会员棚号不存在时，会自动创建会员档案，手机号和密码为空，管理员补齐后才能登录。
- 文件内重复足环、数据库已存在足环、空棚号、空参赛名、空足环号都会进入失败行。
- 导入完成后可以下载错误报告。

`删除所有足环` 只适合重新导入前清理数据。如果已有报名明细引用足环，系统会阻止删除，以保护历史报名记录。

### 4.3 报名导出

后台路径：`报名记录 -> 导出 Excel`

导出内容：

- 顶部包含赛事名称、报名截止时间、各项目数量统计。
- 主表列为：`序号、会员棚号、会员参赛名、足环号码` 加当前赛事所有项目列。
- 单羽项目使用 `✓` 标记。
- 多羽组合按唯一组合导出为一行，`足环号码` 单元格留空，只在对应多羽项目列中用逗号分隔显示组内足环号。
- 递进单羽阶段使用 `✓` 标记；递进多羽阶段按一组一行导出，`足环号码` 单元格留空，在对应阶段项目列中用逗号分隔显示整组足环。第一阶段通过 Excel 导入的确认基准即使没有普通报名主记录，也会出现在导出文件中。
- 所有已使用单元格带实色边框，方便打印和人工核对。

### 4.4 递进报名类别：站站赛、月月赛

递进报名类别用于“站站赛”“月月赛”等逐阶段推进的项目。每个类别可以有多个阶段项目，例如 `福安 1.5K`、`平阳 1.5K`、`龙湾 1K`。阶段项目的 `项目羽数` 可配置为 `1、2、3...`：单羽是一羽一组，多羽是一行整组足环。会员端只显示后台当前开放阶段，并且后续阶段只能从上一阶段已确认的整组足环中继续勾选。

后台配置步骤：

1. 进入 `赛事管理`，先创建或确认目标赛事。
2. 进入 `递进报名类别`，新建类别，填写赛事、类别名称、排序和启用状态。
3. 进入 `报名项目`，为这个类别创建阶段项目：
   - `项目类型` 选择 `递进阶段`
   - `所属递进类别` 选择刚创建的类别
   - `阶段顺序` 从 `1` 开始递增
   - `项目羽数` 填写该阶段每组足环数量；单羽填 `1`，三羽组填 `3`
   - 填写项目名称、金额、排序
   - 金额按组计费，不按组内足环逐羽累计
4. 回到 `递进报名类别` 编辑页，选择 `当前开放阶段`。
5. 第一阶段需要导入基准时，点击该类别的 `导入第一阶段`：
   - 先下载模板
   - 表头固定为 `序号、会员棚号、会员参赛名、足环号码、第一阶段项目名`
   - 如果第一阶段项目羽数大于 `1`，`足环号码` 单元格内用中文逗号或英文逗号分隔整组足环，例如 `2025-13-0530616，2025-13-0530617，2025-13-0530618`
   - 第一阶段列中 `✓、√、1、是、yes` 表示已报名
   - 空值、`×、x、0、否、no` 表示未报名，不写入阶段结果
   - 预览无误后确认导入

导入规则：

- 棚号为准；如果 Excel 中参赛名和系统会员档案不同，最终显示和保存系统中的参赛名。
- 会员不存在时自动创建会员，手机号和密码为空，管理员补齐后才可登录。
- 足环不存在时自动创建并归属到对应会员。
- 导入时会按阶段项目羽数校验每行足环数量；同一行内重复足环失败；完全相同的组重复失败。
- 同一足环允许出现在不同组；如果项目配置了 `每足环最大使用次数`，则按该上限校验。
- 再次确认导入第一阶段时，会清空该类别该第一阶段旧的导入基准数据，再写入新文件数据。
- 第一阶段导入结果默认是 `已确认`，作为第二阶段资格来源。

会员端规则：

- 报名页顶部标签会自动显示 `单羽组`、`多羽组`、每个启用的递进类别和 `已选明细`。
- 点击递进类别后，只显示当前开放阶段的一列勾选矩阵；多羽递进会在同一个格子内分行显示组内足环。
- 第二阶段及以后只能勾选上一阶段已确认的整组足环，不能拆组或重新组队；没有资格时显示空状态。
- 全页面仍使用底部统一提交按钮。
- 本次金额只计算当前开放阶段，不累计历史阶段金额。
- 截止前修改已经确认的当前阶段后，该阶段会回到 `未确认`，需要后台重新确认。

后台确认和导出：

- `报名记录` 中确认报名时，会同时确认该次提交包含的普通报名和递进阶段报名。
- 后台报名明细会把单羽、多羽、递进阶段分区展示。
- 报名 Excel 导出会增加递进阶段项目列，阶段列按项目排序显示；递进多羽按整组展示。

## 5. 会员档案与报名恢复

会员登录后可以从顶部进入 `个人信息`，页面包含：

- 棚号
- 参赛名
- 手机号
- 修改密码
- 报名记录
- 名下足环

后台新建会员、会员 Excel 导入、足环导入自动创建会员后，如果设置了密码，系统会标记该会员首次登录必须修改密码。未完成改密前，会员不能进入赛事列表和报名页面。

报名恢复规则：

- A 浏览器提交报名成功后，B 浏览器登录同一会员进入同一赛事，必须恢复最近一次提交的单羽勾选、多羽组合和已选明细。
- 登录切换账号时，后端会清理旧会员会话，避免微信内偶发串号。
- 会员 API 响应使用 `Cache-Control: no-store`，减少微信 WebView 缓存干扰。

## 6. 公开信息发布

后台路径：`信息发布`

用途：

- 赛事规程
- 成绩发布
- 通知公告

后台使用 Filament 富文本编辑器发布内容，支持标题、列表、引用、表格、图片、文字颜色等常用编辑能力。

公开访问地址：

```text
https://feilesg.com/information
https://feilesg.com/information/{slug}
```

兼容错误拼写：

```text
/infomation -> /information
```

公开接口：

```text
GET /api/public/information
GET /api/public/information/{slug}
```

只有状态为 `发布` 的内容会出现在前台，草稿不会公开。前端渲染富文本前会做 HTML 清洗，降低富文本 XSS 风险。

### 6.1 赛事报名明细发布

后台路径：`赛事管理 -> 明细发布`

用途：

- 报名截止后，把某场赛事的报名明细发布到会员端。
- 会员登录后在 `可报名赛事` 列表中看到 `报名明细` 按钮，点击进入只读明细页。
- 明细页按 `单羽组`、`多羽组`、递进阶段类别分标签展示，并支持按棚号、参赛名、足环号搜索。

发布规则：

- 只有报名截止后的赛事才显示 `明细发布` 按钮。
- 发布时可选择范围：
  - `仅已确认`：只展示后台已确认的普通报名和递进阶段数据，推荐用于正式公开。
  - `全部提交`：展示全部有效提交，并在页面中标注 `已确认` / `未确认` 状态。
- 发布不会生成静态快照；管理员后续在后台修改报名数据后，会员端明细页刷新即可看到最新结果。
- 已发布后可以在赛事列表中 `更新发布设置`、`查看明细` 或 `取消发布`。

展示规则：

- 单羽组：棚号、参赛名、足环号固定在左侧，项目横向滚动，适合手机核对。
- 多羽组：按项目分区，每组展示棚号、参赛名、组内足环和状态。
- 递进阶段：按类别和阶段分区，支持单羽和多羽整组展示。
- 明细页只读，不提供会员修改、确认或下载。

## 7. 生产环境从零部署

下面命令假设项目目录是 `/opt/pigeon-racing`，域名示例是 `feilesg.com`，请替换成你的真实配置。

### 7.1 服务器与域名准备

推荐系统：Ubuntu 22.04 或 Ubuntu 24.04。

安全组至少放行：

```text
22   SSH
80   HTTP
443  HTTPS
```

正式部署后不建议把 `8080` 暴露到公网。推荐让 Docker Nginx 只监听 `127.0.0.1:8080`，再由宿主机 Nginx 或宝塔反向代理。

域名解析：

```text
A 记录：@    -> 服务器公网 IP
A 记录：www  -> 服务器公网 IP
```

创建部署目录：

```bash
sudo mkdir -p /opt/pigeon-racing
sudo chown -R "$USER":"$USER" /opt/pigeon-racing
cd /opt/pigeon-racing
```

### 7.2 安装官方 Docker

不要使用 Snap 版 Docker。Snap 版常见问题是把 Compose 文件解析到 `/var/lib/snapd/void`，导致明明有 `docker-compose.yml` 却报找不到配置文件。

如安装过 Snap Docker，先清除：

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

验证：

```bash
which docker
docker --version
docker compose version
```

正确路径通常是：

```text
/usr/bin/docker
```

如果仍提示 `-bash: /snap/bin/docker: No such file or directory`，执行：

```bash
hash -r
```

仍不生效就退出 SSH 重新登录。

### 7.3 拉取代码

```bash
cd /opt/pigeon-racing
git clone https://github.com/Alcu1n/PigeonRacing.git .
```

确认结构：

```bash
ls
```

应看到：

```text
backend  docker  docker-compose.yml  frontend  README.md
```

验证 Compose：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml config --services
```

应包含：

```text
nginx
app
queue
scheduler
mysql
redis
```

### 7.4 修改 docker-compose.yml

首次启动 MySQL 前必须设置数据库密码。MySQL 官方镜像只会在 volume 首次初始化时读取这些密码，已经启动过再修改 Compose 文件不会自动修改数据库内密码。

编辑：

```bash
nano /opt/pigeon-racing/docker-compose.yml
```

重点确认：

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

`backend/.env` 中的 `DB_PASSWORD` 必须和 `MYSQL_PASSWORD` 完全一致。

### 7.5 创建 backend/.env

```bash
cd /opt/pigeon-racing
cp backend/.env.example backend/.env
nano backend/.env
```

推荐生产配置：

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

`APP_KEY` 是 Laravel 应用加密密钥，用于 Cookie、Session 和应用内部加密。生产环境不能留空，但首次配置时可以先留空，等容器和依赖准备好后执行 `php artisan key:generate --force` 自动写入。

### 7.6 准备 Laravel 运行目录

```bash
cd /opt/pigeon-racing
mkdir -p backend/bootstrap/cache \
  backend/storage/framework/cache \
  backend/storage/framework/sessions \
  backend/storage/framework/views \
  backend/storage/logs
chmod -R 775 backend/bootstrap/cache backend/storage
```

如仍遇到权限问题：

```bash
sudo chown -R "$USER":"$USER" backend/bootstrap/cache backend/storage
chmod -R 775 backend/bootstrap/cache backend/storage
```

### 7.7 安装后端依赖

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml build app queue scheduler
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
```

如果出现 `vendor/autoload.php: Failed to open stream`，说明 Composer 没有成功完成，先解决依赖安装，不要继续执行 Artisan 命令。

如果出现 `bootstrap/cache directory must be present and writable`，重新执行上一节的目录创建和授权命令，然后重试 Composer。

### 7.8 构建会员端资源

未启用 OSS/CDN 时：

```bash
cd /opt/pigeon-racing/frontend/member-h5
npm ci
npm run build
cd /opt/pigeon-racing
```

启用 OSS/CDN 时，推荐使用第 9 节的自动上传脚本。手动构建命令是：

```bash
cd /opt/pigeon-racing/frontend/member-h5
npm ci
VITE_ASSET_BASE_URL=https://cdn.feilesg.com/ npx vite build
cd /opt/pigeon-racing
```

确认产物：

```bash
ls frontend/member-h5/dist
```

应看到：

```text
index.html  assets
```

### 7.9 启动容器

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d
docker compose -f /opt/pigeon-racing/docker-compose.yml ps
```

必须先 `up -d`，再使用 `exec`。如果容器没启动就执行 `exec`，会出现 `service "app" is not running`。

### 7.10 初始化 Laravel

生成 `APP_KEY`：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan key:generate --force
```

创建公开存储链接：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan storage:link
```

发布后台资源：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app php artisan filament:assets
```

清理并缓存配置：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

### 7.11 初始化数据库

先测试连接：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate:status
```

如果返回 `Migration table not found`，通常表示数据库已连通但还没迁移。继续执行：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
```

然后缓存路由和视图：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan route:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan view:cache
```

### 7.12 创建后台管理员

不要在生产环境随意运行演示 seed。使用下面命令创建管理员：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Models\User::query()->updateOrCreate(["email"=>"admin@example.com"], ["name"=>"系统管理员", "password"=>Illuminate\Support\Facades\Hash::make("替换为后台管理员强密码")]); echo "admin ready\n";'
```

访问：

```text
后台：https://feilesg.com/admin
会员端：https://feilesg.com/login
```

### 7.13 宿主机 Nginx 和 HTTPS

安装：

```bash
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
```

创建配置：

```bash
sudo nano /etc/nginx/sites-available/pigeon-racing.conf
```

写入：

```nginx
server {
    listen 80;
    server_name feilesg.com www.feilesg.com;

    client_max_body_size 100m;

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

启用：

```bash
sudo ln -s /etc/nginx/sites-available/pigeon-racing.conf /etc/nginx/sites-enabled/pigeon-racing.conf
sudo nginx -t
sudo systemctl reload nginx
```

申请 HTTPS：

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

刷新缓存：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

`X-Forwarded-Proto $scheme` 很重要。缺少它时，HTTPS 后台可能生成错误的资源地址，导致后台没有样式。

## 8. 生产环境代码更新

生产环境已经部署完成后，不要重新生成 `APP_KEY`，不要删除 MySQL volume，不要重新申请 SSL。每次更新先进入项目目录：

```bash
cd /opt/pigeon-racing
pwd
docker compose -f /opt/pigeon-racing/docker-compose.yml ps
```

确认路径是 `/opt/pigeon-racing`，服务包含 `app`、`nginx`、`mysql`、`redis`、`queue`、`scheduler`。

先拉取代码：

```bash
cd /opt/pigeon-racing
git fetch origin
git status --short
git pull --ff-only
```

如果 `git status --short` 显示服务器本地有未提交修改，不要强行覆盖，先确认这些修改是否应该保留。

### 8.1 只更新前端代码

适用场景：

- 只修改 `frontend/member-h5/` 下的会员端页面、样式、交互、文案。
- 没有修改后端 PHP、数据库迁移、Docker 配置。

如果生产环境启用了 OSS/CDN，执行：

```bash
cd /opt/pigeon-racing
bash scripts/deploy-member-assets-to-oss.sh
docker compose -f /opt/pigeon-racing/docker-compose.yml restart nginx
```

然后到阿里云 CDN 控制台刷新：

```text
操作类型：刷新
操作方式：目录
URL：https://cdn.feilesg.com/assets/
```

如果没有启用 OSS/CDN，执行：

```bash
cd /opt/pigeon-racing/frontend/member-h5
npm ci
npm run build
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml restart nginx
```

验证：

```text
https://feilesg.com/login
```

如果看不到新页面，先强制刷新浏览器；启用 CDN 时再确认 CDN 是否已经刷新。

### 8.2 只更新后端代码

适用场景：

- 修改 `backend/` 下的 Laravel API、Filament 后台、导入导出、模型、服务、路由。
- 可能包含数据库迁移。
- 没有修改会员端前端资源。

执行：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml build app queue scheduler
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d --remove-orphans
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan filament:assets
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan route:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan view:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app queue scheduler nginx
```

验证：

```text
https://feilesg.com/admin
https://feilesg.com/login
```

### 8.3 前后端都更新

适用场景：

- 同时修改了 `backend/` 和 `frontend/member-h5/`。
- 修改了 API 返回结构、会员端页面、后台资源、Docker 配置、Excel 逻辑、信息发布等跨模块功能。
- 涉及递进报名类别、数据库字段或迁移文件时，必须执行本节完整流程，尤其不能跳过 `php artisan migrate --force`。

启用 OSS/CDN 时执行完整流程：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml build app queue scheduler
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
bash scripts/deploy-member-assets-to-oss.sh
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d --remove-orphans
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan filament:assets
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan route:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan view:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app queue scheduler nginx
```

然后刷新 CDN：

```text
操作类型：刷新
操作方式：目录
URL：https://cdn.feilesg.com/assets/
```

未启用 OSS/CDN 时，把 `bash scripts/deploy-member-assets-to-oss.sh` 换成：

```bash
cd /opt/pigeon-racing/frontend/member-h5
npm ci
npm run build
cd /opt/pigeon-racing
```

如果服务器上 `npm run build` 卡在类型检查，可以临时使用：

```bash
cd /opt/pigeon-racing/frontend/member-h5
VITE_ASSET_BASE_URL=https://cdn.feilesg.com/ npx vite build
cd /opt/pigeon-racing
```

这会跳过类型检查，只执行 Vite 构建。正式发布前仍建议在本地或 CI 跑完整 `npm run build`。

### 8.4 更新后检查

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml ps
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 app
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 nginx
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 queue
```

浏览器验证：

```text
https://feilesg.com/login
https://feilesg.com/admin
https://feilesg.com/information
https://feilesg.com/races/{raceId}/details
```

启用 OSS/CDN 时，还要确认：

```text
1. 用户入口仍然是 https://feilesg.com/login。
2. JS/CSS/图片请求来自 https://cdn.feilesg.com/assets/。
3. /api、/sanctum、/admin 请求仍然走 https://feilesg.com。
4. 会员登录、赛事列表、报名提交、后台登录、信息发布都正常。
```

## 9. 阿里云 OSS 与 CDN 静态资源发布

本项目推荐只加速会员端静态资源。用户仍访问 `https://feilesg.com/login`，Laravel API、Sanctum Cookie、后台 `/admin` 都继续走主域名；Vite 构建出的 JS/CSS/图片从 `https://cdn.feilesg.com/assets/` 加载。

### 9.1 安装 ossutil

在生产服务器安装阿里云 `ossutil 2.0`。下面示例适用于 Linux x86_64：

```bash
cd /tmp
curl -o ossutil-2.3.0-linux-amd64.zip https://gosspublic.alicdn.com/ossutil/v2/2.3.0/ossutil-2.3.0-linux-amd64.zip
unzip ossutil-2.3.0-linux-amd64.zip
sudo install -m 0755 ossutil-2.3.0-linux-amd64/ossutil /usr/local/bin/ossutil
ossutil version
```

### 9.2 配置 OSS 环境变量文件

在服务器项目目录创建本地密钥文件。不要把 AccessKey 提交到 Git。

```bash
cd /opt/pigeon-racing
cat > .env.oss.local <<'EOF'
export OSS_BUCKET='filesg'
export OSS_REGION='cn-hongkong'
export OSS_PREFIX='assets/'
export VITE_ASSET_BASE_URL='https://cdn.feilesg.com/'
export OSS_ACCESS_KEY_ID='替换为RAM用户AccessKeyId'
export OSS_ACCESS_KEY_SECRET='替换为RAM用户AccessKeySecret'
EOF
chmod 600 .env.oss.local
```

`OSS_REGION` 必须写阿里云 Region ID。香港是 `cn-hongkong`，不是 `oss-cn-hongkong`。如果写错成 `oss-cn-hongkong`，ossutil 会拼出错误域名 `filesg.oss-oss-cn-hongkong.aliyuncs.com`。

如果 AccessKey 曾出现在聊天记录、截图、日志或命令历史中，应视为已泄露。请在阿里云 RAM 中禁用旧 Key，重新生成最小权限 Key。

最小权限：

```text
oss:ListObjects
oss:PutObject
```

只有使用 `OSS_DELETE_EXTRA=1` 时才需要：

```text
oss:DeleteObject
```

### 9.3 自动构建并上传

```bash
cd /opt/pigeon-racing
bash scripts/deploy-member-assets-to-oss.sh
```

脚本会自动：

1. 读取 `/opt/pigeon-racing/.env.oss.local`。
2. 进入 `frontend/member-h5`。
3. 执行 `VITE_ASSET_BASE_URL=https://cdn.feilesg.com/ npx vite build`。
4. 同步 `dist/assets/` 到 `oss://filesg/assets/`。

脚本默认不删除 OSS 上旧的 hash 文件，避免仍持有旧 HTML 的浏览器资源断裂。如果确定要完全镜像，可以执行：

```bash
OSS_DELETE_EXTRA=1 bash scripts/deploy-member-assets-to-oss.sh
```

### 9.4 CDN 刷新填写方式

OSS 同步成功后，到阿里云 CDN 控制台刷新缓存：

```text
操作类型：刷新
操作方式：目录
URL：https://cdn.feilesg.com/assets/
```

注意：

- 必须填写完整 URL。
- 必须以 `/` 结尾。
- 不要选择“预热 + 目录”，阿里云 CDN 预热只支持具体文件 URL，不支持目录。
- Vite 文件名带 hash，新文件通常不会命中旧缓存；目录刷新主要用于清掉同名图片、CSS 或异常缓存。

### 9.5 CDN 验收

打开：

```text
https://feilesg.com/login
```

在浏览器开发者工具 Network 中确认：

```text
JS/CSS/图片：来自 https://cdn.feilesg.com/assets/
API：来自 https://feilesg.com/api/
CSRF：来自 https://feilesg.com/sanctum/csrf-cookie
后台：仍然访问 https://feilesg.com/admin
```

如果出现 `502 Bad Gateway`，优先排查源站 Nginx、Docker 容器和宿主机反向代理，不要先怀疑 CDN 跨域。CDN 跨域错误通常表现为浏览器控制台脚本加载失败，不会让主域名直接返回 502。

## 10. 宝塔面板部署方式

宝塔推荐只负责域名、SSL 和反向代理，应用仍然用 Docker Compose 运行。

步骤：

1. 按第 7 节在 SSH 中完成 Docker Compose 部署。
2. `docker-compose.yml` 中保持 `127.0.0.1:8080:80`。
3. 宝塔面板添加站点，域名填 `feilesg.com` 和 `www.feilesg.com`。
4. PHP 版本可以选纯静态，因为真实应用由 Docker 承载。
5. 在站点设置中打开反向代理，目标 URL 填：

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

6. 在宝塔站点 SSL 中申请证书，并开启强制 HTTPS。
7. 回到服务器刷新 Laravel 缓存：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

不推荐用宝塔传统 PHP 站点直接跑本项目。这个项目依赖 PHP 扩展、Composer、Node、队列、调度器、Redis 和 Nginx 路由，Docker Compose 已经把这些边界固定好了。

## 11. 备份与恢复

### 11.1 备份

```bash
cd /opt/pigeon-racing
mkdir -p /opt/backups/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T mysql mysqldump -uroot -p你的MYSQL_ROOT_PASSWORD pigeon_registration > /opt/backups/pigeon-racing/pigeon_registration-$(date +%F).sql
tar -czf /opt/backups/pigeon-racing/backend-storage-$(date +%F).tar.gz backend/storage
```

### 11.2 恢复

```bash
cd /opt/pigeon-racing
cat /opt/backups/pigeon-racing/pigeon_registration-YYYY-MM-DD.sql | docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T mysql mysql -uroot -p你的MYSQL_ROOT_PASSWORD pigeon_registration
tar -xzf /opt/backups/pigeon-racing/backend-storage-YYYY-MM-DD.tar.gz -C /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
```

## 12. 常见问题排查

### 12.1 Compose 找不到配置文件

错误：

```text
no configuration file provided: not found
open /var/lib/snapd/void/docker-compose.yml: no such file or directory
```

处理：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml config --services
```

如果仍指向 `/var/lib/snapd/void`，卸载 Snap Docker，安装官方 Docker，并执行 `hash -r`。

### 12.2 app 容器未运行

错误：

```text
service "app" is not running
```

处理：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d
```

### 12.3 vendor/autoload.php 缺失

错误：

```text
vendor/autoload.php: Failed to open stream
```

处理：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml run --rm app composer install --no-dev --optimize-autoloader
```

### 12.4 bootstrap/cache 不可写

错误：

```text
The /var/www/backend/bootstrap/cache directory must be present and writable.
```

处理：

```bash
cd /opt/pigeon-racing
mkdir -p backend/bootstrap/cache \
  backend/storage/framework/cache \
  backend/storage/framework/sessions \
  backend/storage/framework/views \
  backend/storage/logs
chmod -R 775 backend/bootstrap/cache backend/storage
```

### 12.5 Migration table not found

这个通常说明数据库已连通，但还没有执行迁移。处理：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan migrate --force
```

### 12.6 数据库密码错误

错误：

```text
SQLSTATE[HY000] [1045] Access denied for user 'pigeon'
```

原因是 `backend/.env` 的 `DB_PASSWORD` 和 MySQL 中真实密码不一致。

如果数据库没有正式数据，可以重建 volume：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml down -v
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d
```

注意：`down -v` 会删除数据库，只能在空库首次部署时使用。

如果已有数据，登录 MySQL 修改密码：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec mysql mysql -uroot -p
```

执行：

```sql
ALTER USER 'pigeon'@'%' IDENTIFIED BY '替换为backend/.env里的DB_PASSWORD';
FLUSH PRIVILEGES;
EXIT;
```

刷新 Laravel 缓存：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

### 12.7 500 错误

先看日志：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 app
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 nginx
docker compose -f /opt/pigeon-racing/docker-compose.yml logs --tail=100 queue
```

Laravel 日志：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec app tail -n 100 storage/logs/laravel.log
```

如果没有 `storage/logs/laravel.log`，先检查 `storage` 目录权限。

### 12.8 HTTPS 后后台没有样式

检查宿主机 Nginx 是否设置：

```nginx
proxy_set_header X-Forwarded-Proto $scheme;
```

然后执行：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan filament:assets
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app nginx
```

### 12.9 品牌 Logo 上传后前台不显示

先查数据库保存的路径和文件是否存在：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan tinker --execute='
$path = App\Models\AppSetting::getValue(App\Models\AppSetting::BRAND_LOGO_PATH);
dump($path);
dump(Illuminate\Support\Facades\Storage::disk("public")->exists($path));
dump(Illuminate\Support\Facades\Storage::disk("public")->size($path));
dump(Illuminate\Support\Facades\Storage::disk("public")->mimeType($path));
'
```

再测试公网访问：

```bash
curl -I https://feilesg.com/storage/这里替换为上面输出的路径
```

正常应返回 `200`，并且 `Content-Type` 是 `image/png` 或 `image/jpeg`。如果容器内文件正常但公网返回 `403`、`404` 或 HTML，重点检查 Docker Nginx 的 `/storage` 配置和 Laravel 路由缓存。

### 12.10 Excel 导入 413

如果上传或确认导入时报：

```text
413 Request Entity Too Large
```

需要同时检查：

- 宿主机 Nginx 的 `client_max_body_size`。
- Docker Nginx 的 `client_max_body_size`。
- PHP 的 `upload_max_filesize`、`post_max_size`、`memory_limit`。
- 是否已经拉取包含服务端预览缓存机制的最新代码。

更新这类运行配置后必须重建并重启：

```bash
cd /opt/pigeon-racing
docker compose -f /opt/pigeon-racing/docker-compose.yml build app queue scheduler
docker compose -f /opt/pigeon-racing/docker-compose.yml up -d --remove-orphans
docker compose -f /opt/pigeon-racing/docker-compose.yml restart app queue scheduler nginx
```

### 12.11 登录循环、419、手机提交失败

检查：

```env
APP_URL=https://feilesg.com
FRONTEND_URL=https://feilesg.com
SANCTUM_STATEFUL_DOMAINS=feilesg.com,www.feilesg.com
SESSION_DOMAIN=
```

然后刷新缓存：

```bash
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan optimize:clear
docker compose -f /opt/pigeon-racing/docker-compose.yml exec -T app php artisan config:cache
```

## 13. 常用验证命令

后端测试：

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/backend
php artisan test
composer validate --no-check-publish --strict
```

前端测试和构建：

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/frontend/member-h5
npm test
npm run build
```

查看会员 API 路由：

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/backend
php artisan route:list --path=api/member
```

查看公开信息 API 路由：

```bash
cd /Users/alcuin/Coding/在线赛事报名系统/backend
php artisan route:list --path=api/public
```
