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
3. Confirm a pending registration. / 确认一条待确认报名。
4. Edit a race project and verify `config_version` policy before member submission in later tests. / 后续测试可修改赛事项目并验证会员提交前的 `config_version` 策略。

### 5. Reset Local Data / 重置本地数据

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

### 6. Frontend-Only Development / 仅前端开发

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

### 7. Verification Commands / 验证命令

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
