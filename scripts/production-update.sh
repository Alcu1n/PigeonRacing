#!/usr/bin/env bash
# [IN]: Production update mode and host-local deployment configuration / 生产更新模式与服务器本地部署配置
# [OUT]: One-command frontend, backend, or full production update flow / 前端、后端或完整生产更新的一键流程
# [POS]: Production deployment orchestrator / 生产部署编排脚本
# Protocol: When updating me, sync this header + parent folder's .folder.md
# 协议:更新本文件时，同步更新此头注释及所属文件夹的 .folder.md

set -euo pipefail

ROOT_DIR="${PIGEON_ROOT:-/opt/pigeon-racing}"
COMPOSE_FILE="${COMPOSE_FILE:-$ROOT_DIR/docker-compose.yml}"
MODE="${1:-}"
PULL_CODE="${PULL_CODE:-1}"
FRONTEND_ASSET_MODE="${FRONTEND_ASSET_MODE:-oss}"

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

usage() {
    cat <<'USAGE'
Usage:
  bash scripts/production-update.sh frontend
  bash scripts/production-update.sh backend
  bash scripts/production-update.sh full

Environment variables:
  PIGEON_ROOT=/opt/pigeon-racing      Production project directory.
  COMPOSE_FILE=/path/docker-compose.yml
  PULL_CODE=0                         Skip git fetch/pull.
  FRONTEND_ASSET_MODE=oss|local       Default: oss.
USAGE
}

compose() {
    docker compose -f "$COMPOSE_FILE" "$@"
}

ensure_mode() {
    case "$MODE" in
        frontend | backend | full)
            ;;
        *)
            usage
            fail "Update mode must be one of: frontend, backend, full"
            ;;
    esac
}

prepare_workspace() {
    [[ -d "$ROOT_DIR" ]] || fail "Project directory does not exist: $ROOT_DIR"
    [[ -f "$COMPOSE_FILE" ]] || fail "Compose file does not exist: $COMPOSE_FILE"

    cd "$ROOT_DIR"

    printf 'Project: %s\n' "$ROOT_DIR"
    printf 'Mode: %s\n' "$MODE"
    compose ps

    if [[ "$PULL_CODE" != "1" ]]; then
        printf 'Skipping git pull because PULL_CODE=%s\n' "$PULL_CODE"
        return
    fi

    git fetch origin

    if [[ -n "$(git status --short)" ]]; then
        git status --short
        fail "Production worktree has local changes. Resolve them before updating, or rerun with PULL_CODE=0 if intentional."
    fi

    git pull --ff-only
}

deploy_frontend() {
    local restart_nginx="${1:-1}"

    case "$FRONTEND_ASSET_MODE" in
        oss)
            bash "$ROOT_DIR/scripts/deploy-member-assets-to-oss.sh"
            ;;
        local)
            cd "$ROOT_DIR/frontend/member-h5"
            npm ci
            npm run build
            cd "$ROOT_DIR"
            ;;
        *)
            fail "FRONTEND_ASSET_MODE must be oss or local"
            ;;
    esac

    if [[ "$restart_nginx" == "1" ]]; then
        compose restart nginx
    fi
}

prepare_backend() {
    compose build app queue scheduler
    compose run --rm app composer install --no-dev --optimize-autoloader
}

apply_backend() {
    compose up -d --remove-orphans
    compose exec -T app php artisan migrate --force
    compose exec -T app php artisan filament:assets
    compose exec -T app php artisan optimize:clear
    compose exec -T app php artisan config:cache
    compose exec -T app php artisan route:cache
    compose exec -T app php artisan view:cache
    compose restart app queue scheduler nginx
}

verify_services() {
    compose ps
    compose logs --tail=50 app
    compose logs --tail=50 nginx
    compose logs --tail=50 queue
}

main() {
    ensure_mode
    prepare_workspace

    case "$MODE" in
        frontend)
            deploy_frontend 1
            ;;
        backend)
            prepare_backend
            apply_backend
            ;;
        full)
            prepare_backend
            deploy_frontend 0
            apply_backend
            ;;
    esac

    verify_services

    if [[ "$MODE" == "frontend" || "$MODE" == "full" ]] && [[ "$FRONTEND_ASSET_MODE" == "oss" ]]; then
        cat <<'NOTICE'
CDN refresh still needs to be triggered in Alibaba Cloud:
  Type: Directory refresh
  URL:  https://cdn.feilesg.com/assets/
NOTICE
    fi

    printf 'Production update finished: %s\n' "$MODE"
}

main "$@"
