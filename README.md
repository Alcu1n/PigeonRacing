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

## Run / 运行

- Backend dependencies: `cd backend && composer install` (Docker image includes required PHP extensions). / 后端依赖：`cd backend && composer install`（Docker 镜像包含所需 PHP 扩展）。
- Frontend dependencies: `cd frontend/member-h5 && npm install`. / 前端依赖：`cd frontend/member-h5 && npm install`。
- Frontend dev: `npm run dev -- --port 5173`. / 前端开发：`npm run dev -- --port 5173`。
- Verification: `cd backend && php artisan test`; `cd frontend/member-h5 && npm test && npm run build`. / 验证：`cd backend && php artisan test`；`cd frontend/member-h5 && npm test && npm run build`。
- Stack: build frontend, copy `.env.example` to `.env`, set `APP_KEY`, then `docker compose up --build`. / 全栈：先构建前端，复制 `.env.example` 为 `.env`，设置 `APP_KEY`，再运行 `docker compose up --build`。
